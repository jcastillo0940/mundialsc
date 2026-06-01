<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegistrationCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || in_array($user->role, ['admin', 'cashier'], true) || $this->isRegistrationComplete($user)) {
            return $next($request);
        }

        return new JsonResponse([
            'message' => 'Debes completar tu registro para continuar.',
        ], 403);
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
}
