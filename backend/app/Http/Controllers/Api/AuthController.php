<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Support\Audit;
use App\Support\ContestRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AuthController extends Controller
{
    public function __construct(
        private readonly ContestRules $contestRules,
    ) {
    }

    public function register(Request $request): JsonResponse
    {
        $this->ensureRegistrationWindowIsOpen();

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'document_type' => ['required', 'in:cedula,passport,residente'],
            'cedula' => ['required', 'string', 'max:40', 'unique:users,cedula'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:12', 'regex:/^\+507[0-9]{8}$/', 'unique:users,phone'],
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'birthdate' => ['required', 'date', 'before_or_equal:'.now('America/Panama')->subYears(18)->toDateString()],
            'resides_in_panama' => ['required', 'accepted'],
            'is_employee' => ['required', 'boolean'],
            'accepted_terms' => ['required', 'accepted'],
            'group_stage_goal_prediction' => ['required', 'integer', 'min:0', 'max:300'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'recaptcha_token' => ['nullable', 'string'],
        ]);

        $this->verifyCaptcha($request);
        $this->verifyPanamaIp($request);

        $data['cedula'] = $this->normalizeIdentityNumber($data['document_type'], $data['cedula']);
        $this->validateIdentityNumber($data['document_type'], $data['cedula']);

        $this->ensureEligibleIdentity($data['cedula'], (bool) $data['is_employee']);

        $avatarPath = $request->file('avatar')?->store('avatars', 'public');

        $registrationNow = now();

        $user = User::query()->create([
            'name' => $data['full_name'],
            'cedula' => $data['cedula'],
            'document_type' => $data['document_type'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'avatar_path' => $avatarPath,
            'role' => 'client',
            'password' => Hash::make($data['password']),
            'is_active' => true,
            'birthdate' => $data['birthdate'],
            'resides_in_panama' => true,
            'is_employee' => false,
            'accepted_terms_at' => now(),
            'registration_completed_at' => $registrationNow,
            'registration_order_key' => $registrationNow->format('Uu').'-'.Str::random(8),
            'group_stage_goal_prediction' => $data['group_stage_goal_prediction'],
        ]);

        $this->createWalletIfMissing($user);

        $token = $user->createToken('client-app')->plainTextToken;

        Audit::log('user.registered', 'user', $user->id, $user, $request);

        return response()->json([
            'message' => 'Registro completado.',
            'token' => $token,
            'user' => $this->serializeUser($user->fresh('wallet')),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required_without:cedula', 'nullable', 'email'],
            'cedula' => ['required_without:email', 'nullable', 'string', 'max:40'],
            'password' => ['required', 'string'],
        ]);

        if (! empty($data['cedula'])) {
            $data['cedula'] = Str::upper(trim($data['cedula']));
        }

        $user = User::query()
            ->when(
                ! empty($data['email']),
                fn ($query) => $query->where('email', $data['email']),
                fn ($query) => $query->where('cedula', $data['cedula'])
            )
            ->first();

        try {
            $passwordMatches = $user ? Hash::check($data['password'], $user->password) : false;
        } catch (RuntimeException) {
            $passwordMatches = false;
        }

        if (! $user || ! $passwordMatches) {
            throw ValidationException::withMessages([
                'credentials' => 'Las credenciales no son validas.',
            ]);
        }

        if (in_array($user->role, ['admin', 'cashier'], true)) {
            throw ValidationException::withMessages([
                'account' => 'Esta cuenta solo puede ingresar por el acceso interno del backoffice.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'account' => 'La cuenta esta pendiente de verificacion OTP.',
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken('client-app')->plainTextToken;

        Audit::log('user.logged_in', 'user', $user->id, $user, $request);

        return response()->json([
            'message' => 'Bienvenido.',
            'token' => $token,
            'user' => $this->serializeUser($user->fresh('wallet')),
        ]);
    }

    public function google(Request $request): JsonResponse
    {
        if (! config('contest.allow_google_auth')) {
            throw ValidationException::withMessages([
                'credential' => 'El acceso con Google esta deshabilitado para esta promocion porque el registro requiere datos legales adicionales.',
            ]);
        }

        $data = $request->validate([
            'credential' => ['required', 'string'],
            'recaptcha_token' => ['nullable', 'string'],
        ]);

        $this->verifyCaptcha($request);

        $payload = $this->verifyGoogleCredential($data['credential']);

        $user = User::query()->where('google_id', $payload['sub'])->first();

        if (! $user && ! empty($payload['email'])) {
            $user = User::query()->where('email', $payload['email'])->first();

            if ($user && $user->google_id && $user->google_id !== $payload['sub']) {
                throw ValidationException::withMessages([
                    'credential' => 'Ese correo ya esta vinculado con otra cuenta de Google.',
                ]);
            }
        }

        if ($user && ! $user->is_active) {
            throw ValidationException::withMessages([
                'account' => 'La cuenta esta inactiva.',
            ]);
        }

        if ($user && in_array($user->role, ['admin', 'cashier'], true)) {
            throw ValidationException::withMessages([
                'account' => 'Esta cuenta solo puede ingresar por el acceso interno del backoffice.',
            ]);
        }

        if (! $user || ! $this->isRegistrationComplete($user)) {
            $this->ensureRegistrationWindowIsOpen();
        }

        if (! $user) {
            $user = User::query()->create([
                'name' => Str::limit($payload['name'] ?? Str::before($payload['email'], '@'), 150, ''),
                'cedula' => $this->buildGoogleCedula($payload['sub']),
                'document_type' => 'passport',
                'email' => $payload['email'],
                'google_id' => $payload['sub'],
                'phone' => null,
                'avatar_path' => null,
                'role' => 'client',
                'password' => Hash::make(Str::random(40)),
                'is_active' => 1,
                'resides_in_panama' => false,
                'is_employee' => false,
                'email_verified_at' => now(),
                'last_login_at' => now(),
            ]);

            $this->createWalletIfMissing($user);
            Audit::log('user.google_registered', 'user', $user->id, $user, $request);
        } else {
            $updates = [
                'google_id' => $payload['sub'],
                'last_login_at' => now(),
            ];

            if (! empty($payload['email']) && $user->email !== $payload['email']) {
                $updates['email'] = $payload['email'];
            }

            if (! empty($payload['name']) && $user->name !== $payload['name']) {
                $updates['name'] = Str::limit($payload['name'], 150, ''); // columna name = full_name
            }

            if (! $user->email_verified_at) {
                $updates['email_verified_at'] = now();
            }

            $user->forceFill($updates)->save();
        }

        $token = $user->createToken('client-app')->plainTextToken;

        Audit::log('user.google_logged_in', 'user', $user->id, $user, $request);

        return response()->json([
            'message' => $this->isRegistrationComplete($user)
                ? 'Bienvenido.'
                : 'Completa tu registro para participar en la promocion.',
            'token' => $token,
            'user' => $this->serializeUser($user->fresh('wallet')),
            'requires_registration_completion' => ! $this->isRegistrationComplete($user),
        ]);
    }

    public function completeGoogleRegistration(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->google_id) {
            throw ValidationException::withMessages([
                'account' => 'Esta cuenta no fue creada con Google.',
            ]);
        }

        $this->ensureRegistrationWindowIsOpen();

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'document_type' => ['required', 'in:cedula,passport,residente'],
            'cedula' => ['required', 'string', 'max:40', Rule::unique('users', 'cedula')->ignore($user->id)],
            'phone' => ['required', 'string', 'max:12', 'regex:/^\+507[0-9]{8}$/', Rule::unique('users', 'phone')->ignore($user->id)],
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'birthdate' => ['required', 'date', 'before_or_equal:'.now('America/Panama')->subYears(18)->toDateString()],
            'resides_in_panama' => ['required', 'accepted'],
            'is_employee' => ['required', 'boolean'],
            'accepted_terms' => ['required', 'accepted'],
            'group_stage_goal_prediction' => ['required', 'integer', 'min:0', 'max:300'],
            'recaptcha_token' => ['nullable', 'string'],
        ]);

        $this->verifyCaptcha($request);
        $this->verifyPanamaIp($request);

        $data['cedula'] = $this->normalizeIdentityNumber($data['document_type'], $data['cedula']);
        $this->validateIdentityNumber($data['document_type'], $data['cedula']);
        $this->ensureEligibleIdentity($data['cedula'], (bool) $data['is_employee']);

        $previousAvatarPath = $user->avatar_path;
        $avatarPath = $request->file('avatar')?->store('avatars', 'public');
        $registrationNow = now();

        $user->forceFill([
            'name' => $data['full_name'],
            'cedula' => $data['cedula'],
            'document_type' => $data['document_type'],
            'phone' => $data['phone'],
            'avatar_path' => $avatarPath,
            'birthdate' => $data['birthdate'],
            'resides_in_panama' => true,
            'is_employee' => false,
            'accepted_terms_at' => $registrationNow,
            'registration_completed_at' => $registrationNow,
            'registration_order_key' => $registrationNow->format('Uu').'-'.Str::random(8),
            'group_stage_goal_prediction' => (int) $data['group_stage_goal_prediction'],
        ])->save();

        $this->createWalletIfMissing($user);

        if ($previousAvatarPath && $previousAvatarPath !== $avatarPath) {
            Storage::disk('public')->delete($previousAvatarPath);
        }

        Audit::log('user.google_registration_completed', 'user', $user->id, $user, $request);

        return response()->json([
            'message' => 'Registro completado.',
            'user' => $this->serializeUser($user->fresh(['wallet', 'branch'])),
            'requires_registration_completion' => false,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->serializeUser($request->user()->loadMissing('wallet', 'branch')),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:12', 'regex:/^\+507[0-9]{8}$/', Rule::unique('users', 'phone')->ignore($user->id)],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $previousAvatarPath = $user->avatar_path;

        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $request->file('avatar')?->store('avatars', 'public');
        }

        unset($data['avatar']);

        $user->forceFill([
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'avatar_path' => $data['avatar_path'] ?? $user->avatar_path,
        ])->save();

        if (! empty($data['avatar_path']) && $previousAvatarPath && $previousAvatarPath !== $data['avatar_path']) {
            Storage::disk('public')->delete($previousAvatarPath);
        }

        Audit::log('user.profile_updated', 'user', $user->id, $user, $request);

        return response()->json([
            'message' => 'Cuenta actualizada.',
            'user' => $this->serializeUser($user->fresh(['wallet', 'branch'])),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->user()->currentAccessToken()?->delete();

        Audit::log('user.logged_out', 'user', $user?->id, $user, $request);

        return response()->json([
            'message' => 'Sesion cerrada.',
        ]);
    }

    private function verifyGoogleCredential(string $credential): array
    {
        $clientId = config('services.google.client_id');

        if (! $clientId) {
            throw ValidationException::withMessages([
                'credential' => 'Google Sign-In no esta configurado en el servidor.',
            ]);
        }

        $response = Http::get(config('services.google.tokeninfo_url'), [
            'id_token' => $credential,
        ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'credential' => 'No se pudo validar la credencial de Google.',
            ]);
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json();

        if (($payload['aud'] ?? null) !== $clientId) {
            throw ValidationException::withMessages([
                'credential' => 'La credencial de Google no corresponde a esta aplicacion.',
            ]);
        }

        if (! in_array($payload['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            throw ValidationException::withMessages([
                'credential' => 'El emisor de la credencial de Google no es valido.',
            ]);
        }

        if (($payload['email_verified'] ?? 'false') !== 'true' || empty($payload['email']) || empty($payload['sub'])) {
            throw ValidationException::withMessages([
                'credential' => 'La cuenta de Google no tiene un correo verificado.',
            ]);
        }

        return $payload;
    }

    private function verifyCaptcha(Request $request): void
    {
        $secret = (string) config('contest.recaptcha_secret', '');

        if ($secret === '') {
            return;
        }

        $token = (string) $request->input('recaptcha_token', '');

        if ($token === '') {
            throw ValidationException::withMessages([
                'recaptcha_token' => 'No fue posible validar el CAPTCHA.',
            ]);
        }

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $request->ip(),
        ]);

        $body = $response->json() ?? [];

        if (! $response->successful() || ! (bool) ($body['success'] ?? false) || (float) ($body['score'] ?? 0) < 0.5) {
            throw ValidationException::withMessages([
                'recaptcha_token' => 'La validacion CAPTCHA no fue aprobada.',
            ]);
        }
    }

    private function verifyPanamaIp(Request $request): void
    {
        if (! config('contest.block_non_panama_ip')) {
            return;
        }

        $country = strtoupper((string) (
            $request->header('CF-IPCountry')
            ?: $request->header('X-Vercel-IP-Country')
            ?: $request->header('X-IP-Country')
        ));

        if ($country !== '' && $country !== 'PA') {
            throw ValidationException::withMessages([
                'country' => 'El registro esta habilitado solo para conexiones desde Panama.',
            ]);
        }
    }

    private function ensureRegistrationWindowIsOpen(): void
    {
        if (now('America/Panama')->greaterThan($this->contestRules->registrationDeadline())) {
            throw ValidationException::withMessages([
                'registration' => 'El registro para la promocion cerro el 10 de junio de 2026.',
            ]);
        }
    }

    private function ensureEligibleIdentity(string $identityNumber, bool $isEmployee): void
    {
        if ($isEmployee) {
            throw ValidationException::withMessages([
                'is_employee' => 'Los empleados directos de Super Carnes no pueden participar en esta promocion.',
            ]);
        }

        $employeeDenylist = array_filter(array_map(
            fn (string $value) => Str::upper(trim($value)),
            explode(',', (string) config('contest.employee_identity_denylist', '')),
        ));

        if (in_array($identityNumber, $employeeDenylist, true)) {
            throw ValidationException::withMessages([
                'cedula' => 'Este documento no es elegible para participar en la promocion.',
            ]);
        }
    }

    private function createWalletIfMissing(User $user): void
    {
        if ($user->wallet()->exists()) {
            return;
        }

        Wallet::query()->create([
            'user_id' => $user->id,
            'goals_balance' => 0,
            'shots_balance' => 0,
            'lifetime_goals_earned' => 0,
            'lifetime_shots_earned' => 0,
        ]);
    }

    private function isRegistrationComplete(User $user): bool
    {
        return $user->registration_completed_at !== null
            && $user->accepted_terms_at !== null
            && $user->birthdate !== null
            && (bool) $user->resides_in_panama
            && ! empty($user->phone)
            && ! empty($user->avatar_path)
            && $user->group_stage_goal_prediction !== null
            && ! empty($user->cedula);
    }

    private function buildGoogleCedula(string $googleId): string
    {
        return Str::limit('google-'.$googleId, 40, '');
    }

    private function normalizeIdentityNumber(string $documentType, string $value): string
    {
        $normalized = Str::upper(trim($value));

        if ($documentType === 'cedula') {
            return preg_replace('/[^A-Z0-9-]/', '', $normalized) ?? '';
        }

        return preg_replace('/[^A-Z0-9-]/', '', $normalized) ?? '';
    }

    private function validateIdentityNumber(string $documentType, string $identityNumber): void
    {
        if ($documentType === 'cedula') {
            if (! preg_match('/^(\d{1,2}-\d{1,4}-\d{1,6}|PE-\d{1,4}-\d{1,6}|E-\d{1,4}-\d{1,6}|N-\d{1,4}-\d{1,6})$/', $identityNumber)) {
                throw ValidationException::withMessages([
                    'cedula' => 'La cedula debe usar formato de Panama, por ejemplo 8-864-1164, PE-123-456, E-123-456 o N-123-456.',
                ]);
            }

            return;
        }

        if ($documentType === 'passport') {
            if (! preg_match('/^(?=.*[A-Z])[A-Z0-9-]{5,20}$/', $identityNumber)) {
                throw ValidationException::withMessages([
                    'cedula' => 'El pasaporte debe ser alfanumerico y contener al menos una letra.',
                ]);
            }

            return;
        }

        if (! preg_match('/^(?=.*[A-Z])(?=.*\d)[A-Z0-9-]{3,25}$/', $identityNumber)) {
            throw ValidationException::withMessages([
                'cedula' => 'El documento de residente debe mezclar letras y numeros. Puedes usar guiones si aplica.',
            ]);
        }
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'role' => $user->role,
            'full_name' => $user->name,
            'cedula' => $user->cedula,
            'document_type' => $user->document_type,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_path ? url('/media/'.$user->avatar_path) : null,
            'birthdate' => optional($user->birthdate)->toDateString(),
            'resides_in_panama' => (bool) $user->resides_in_panama,
            'accepted_terms_at' => optional($user->accepted_terms_at)?->toIso8601String(),
            'group_stage_goal_prediction' => $user->group_stage_goal_prediction,
            'registration_completed_at' => optional($user->registration_completed_at)?->toIso8601String(),
            'predictions_completed_at' => optional($user->predictions_completed_at)?->toIso8601String(),
            'disqualified_at' => optional($user->disqualified_at)?->toIso8601String(),
            'branch' => $user->branch ? [
                'id' => $user->branch->id,
                'name' => $user->branch->name,
                'code' => $user->branch->code,
            ] : null,
            'wallet' => $user->wallet ? [
                'goals_balance' => $user->wallet->goals_balance,
                'shots_balance' => $user->wallet->shots_balance,
                'lifetime_goals_earned' => $user->wallet->lifetime_goals_earned,
                'lifetime_shots_earned' => $user->wallet->lifetime_shots_earned,
            ] : null,
        ];
    }
}
