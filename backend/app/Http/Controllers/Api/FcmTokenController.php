<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FcmToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FcmTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:50'],
        ]);

        $record = FcmToken::query()->updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $request->user()->id,
                'device_name' => $data['device_name'] ?? null,
                'platform' => $data['platform'] ?? null,
                'user_agent' => substr((string) $request->userAgent(), 0, 500) ?: null,
                'is_enabled' => true,
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Token FCM registrado.',
            'token' => [
                'id' => $record->id,
                'is_enabled' => $record->is_enabled,
            ],
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        FcmToken::query()
            ->where('user_id', $request->user()->id)
            ->where('token', $data['token'])
            ->delete();

        return response()->json(['message' => 'Token FCM eliminado.']);
    }
}
