<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;

class PublicSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'auth_bg_youtube_id' => SiteSetting::get('auth_bg_youtube_id', ''),
        ]);
    }

    public function updateYoutubeId(): JsonResponse
    {
        $validated = request()->validate([
            'youtube_id' => ['required', 'string', 'max:20'],
        ]);

        SiteSetting::set('auth_bg_youtube_id', $validated['youtube_id']);

        return response()->json(['message' => 'Video actualizado.']);
    }
}
