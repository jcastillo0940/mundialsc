<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
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
        if (now('America/Panama')->greaterThan($this->contestRules->registrationDeadline())) {
            throw ValidationException::withMessages([
                'registration' => 'El registro para la promocion cerro el 10 de junio de 2026.',
            ]);
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'document_type' => ['required', 'in:cedula,passport,residente'],
            'cedula' => ['required', 'string', 'max:40', 'unique:users,cedula'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'birthdate' => ['required', 'date', 'before_or_equal:'.now('America/Panama')->subYears(18)->toDateString()],
            'resides_in_panama' => ['required', 'accepted'],
            'is_employee' => ['required', 'boolean'],
            'accepted_terms' => ['required', 'accepted'],
            'group_stage_goal_prediction' => ['required', 'integer', 'min:0', 'max:300'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $data['cedula'] = $this->normalizeIdentityNumber($data['document_type'], $data['cedula']);
        $this->validateIdentityNumber($data['document_type'], $data['cedula']);

        if ((bool) $data['is_employee']) {
            throw ValidationException::withMessages([
                'is_employee' => 'Los empleados directos de Super Carnes no pueden participar en esta promocion.',
            ]);
        }

        $avatarPath = $request->file('avatar')?->store('avatars', 'public');

        $user = User::query()->create([
            'full_name' => $data['full_name'],
            'cedula' => $data['cedula'],
            'document_type' => $data['document_type'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'avatar_path' => $avatarPath,
            'role' => 'client',
            'password_hash' => Hash::make($data['password']),
            'is_active' => 1,
            'birthdate' => $data['birthdate'],
            'resides_in_panama' => true,
            'is_employee' => false,
            'accepted_terms_at' => now(),
            'registration_completed_at' => now(),
            'group_stage_goal_prediction' => $data['group_stage_goal_prediction'],
        ]);

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
            $passwordMatches = $user ? Hash::check($data['password'], $user->password_hash) : false;
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
                'account' => 'La cuenta esta inactiva.',
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
        ]);

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

        if (! $user) {
            $user = User::query()->create([
                'full_name' => Str::limit($payload['name'] ?? Str::before($payload['email'], '@'), 150, ''),
                'cedula' => $this->buildGoogleCedula($payload['sub']),
                'document_type' => 'passport',
                'email' => $payload['email'],
                'google_id' => $payload['sub'],
                'phone' => null,
                'avatar_path' => null,
                'role' => 'client',
                'password_hash' => Hash::make(Str::random(40)),
                'is_active' => 1,
                'resides_in_panama' => false,
                'is_employee' => false,
                'email_verified_at' => now(),
                'last_login_at' => now(),
            ]);

            Audit::log('user.google_registered', 'user', $user->id, $user, $request);
        } else {
            $updates = [
                'google_id' => $payload['sub'],
                'last_login_at' => now(),
            ];

            if (! empty($payload['email']) && $user->email !== $payload['email']) {
                $updates['email'] = $payload['email'];
            }

            if (! empty($payload['name']) && $user->full_name !== $payload['name']) {
                $updates['full_name'] = Str::limit($payload['name'], 150, '');
            }

            if (! $user->email_verified_at) {
                $updates['email_verified_at'] = now();
            }

            $user->forceFill($updates)->save();
        }

        $token = $user->createToken('client-app')->plainTextToken;

        Audit::log('user.google_logged_in', 'user', $user->id, $user, $request);

        return response()->json([
            'message' => 'Bienvenido.',
            'token' => $token,
            'user' => $this->serializeUser($user->fresh('wallet')),
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
            'phone' => ['nullable', 'string', 'max:30'],
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

    private function buildGoogleCedula(string $googleId): string
    {
        return Str::limit('google-'.$googleId, 40, '');
    }

    private function normalizeIdentityNumber(string $documentType, string $value): string
    {
        $normalized = Str::upper(trim($value));

        if ($documentType === 'cedula') {
            return preg_replace('/[^0-9-]/', '', $normalized) ?? '';
        }

        return preg_replace('/[^A-Z0-9-]/', '', $normalized) ?? '';
    }

    private function validateIdentityNumber(string $documentType, string $identityNumber): void
    {
        if ($documentType === 'cedula') {
            if (! preg_match('/^\d{1,2}-\d{1,4}-\d{1,6}$/', $identityNumber)) {
                throw ValidationException::withMessages([
                    'cedula' => 'La cedula debe usar formato de Panama, por ejemplo 8-864-1164, 9-150-523 o 7-23-111.',
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
            'full_name' => $user->full_name,
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
