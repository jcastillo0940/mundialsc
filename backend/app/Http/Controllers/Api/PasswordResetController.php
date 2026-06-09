<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MailLog;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function requestReset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if ($user) {
            $token = Password::broker()->createToken($user);
            $user->notify(new ResetPasswordNotification($token));

            MailLog::query()->create([
                'channel' => 'password_reset',
                'event_type' => 'queued',
                'recipient_email' => $user->email,
                'subject' => 'Recupera tu contraseña',
                'status' => 'queued',
                'user_id' => $user->id,
                'meta' => [
                    'action' => 'request_reset',
                ],
                'sent_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Si tu correo esta en nuestra base de datos, te enviaremos un enlace para cambiar tu contraseña.',
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::broker()->reset(
            $data,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                MailLog::query()->create([
                    'channel' => 'password_reset',
                    'event_type' => 'completed',
                    'recipient_email' => $user->email,
                    'subject' => 'Recupera tu contraseña',
                    'status' => 'completed',
                    'user_id' => $user->id,
                    'meta' => [
                        'action' => 'reset_completed',
                    ],
                    'sent_at' => now(),
                ]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'No pudimos completar la solicitud. Intenta nuevamente.',
            ], 422);
        }

        return response()->json([
            'message' => 'Tu contraseña fue actualizada correctamente.',
        ]);
    }
}
