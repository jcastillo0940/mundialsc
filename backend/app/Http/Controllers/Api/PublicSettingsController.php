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
            'hero_video_url' => SiteSetting::get('hero_video_url', ''),
            'seo_site_title' => SiteSetting::get('seo_site_title', ''),
            'seo_meta_description' => SiteSetting::get('seo_meta_description', ''),
            'seo_meta_keywords' => SiteSetting::get('seo_meta_keywords', ''),
            'seo_og_title' => SiteSetting::get('seo_og_title', ''),
            'seo_og_description' => SiteSetting::get('seo_og_description', ''),
            'seo_og_image' => SiteSetting::get('seo_og_image', ''),
            'terms_and_conditions' => SiteSetting::get('terms_and_conditions', ''),
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
