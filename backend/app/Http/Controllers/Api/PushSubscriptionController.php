<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PushSubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $hasFcm = $user->fcmTokens()->where('is_enabled', true)->exists();
        $hasVapid = $user->pushSubscriptions()->where('is_enabled', true)->exists();

        return response()->json([
            'enabled' => $hasFcm || $hasVapid,
            'subscriptions' => $user->pushSubscriptions()
                ->orderByDesc('updated_at')
                ->get()
                ->map(fn (PushSubscription $subscription) => $this->serializeSubscription($subscription))
                ->values(),
            'push_supported' => true,
            'vapid_public_key' => config('services.push.vapid_public_key'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', 'string', 'max:32'],
        ]);

        if (trim((string) config('services.push.vapid_public_key')) === '') {
            throw ValidationException::withMessages([
                'push' => 'Las notificaciones push aun no estan configuradas en el servidor.',
            ]);
        }

        $subscription = PushSubscription::query()->updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id' => $user->id,
                'p256dh_key' => $data['keys']['p256dh'],
                'auth_key' => $data['keys']['auth'],
                'content_encoding' => $data['content_encoding'] ?? 'aes128gcm',
                'user_agent' => substr((string) $request->userAgent(), 0, 500) ?: null,
                'is_enabled' => true,
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Notificaciones push activadas.',
            'subscription' => $this->serializeSubscription($subscription->fresh()),
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
        ]);

        PushSubscription::query()
            ->where('user_id', $user->id)
            ->where('endpoint', $data['endpoint'])
            ->delete();

        return response()->json([
            'message' => 'Suscripcion push eliminada.',
        ]);
    }

    private function serializeSubscription(PushSubscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'endpoint' => $subscription->endpoint,
            'is_enabled' => $subscription->is_enabled,
            'last_seen_at' => optional($subscription->last_seen_at)?->toIso8601String(),
            'created_at' => optional($subscription->created_at)?->toIso8601String(),
            'updated_at' => optional($subscription->updated_at)?->toIso8601String(),
        ];
    }
}
