<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecaptchaSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_accepts_successful_v2_invisible_captcha_without_score(): void
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'challenge_ts' => now()->toIso8601String(),
                'hostname' => 'localhost',
            ]),
        ]);

        config()->set('contest.recaptcha_secret', 'test-secret');

        User::query()->create([
            'name' => 'Jugador Demo',
            'email' => 'jugador@example.com',
            'document_type' => 'cedula',
            'cedula' => '8-864-1164',
            'password' => Hash::make('secreto-demo'),
            'role' => 'client',
            'is_active' => true,
            'resides_in_panama' => true,
            'is_employee' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'jugador@example.com',
            'password' => 'secreto-demo',
            'recaptcha_token' => 'token-v2-demo',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Bienvenido.');
    }

    public function test_login_requires_captcha_token_when_secret_is_configured(): void
    {
        config()->set('contest.recaptcha_secret', 'test-secret');

        User::query()->create([
            'name' => 'Jugador Demo',
            'email' => 'jugador@example.com',
            'document_type' => 'cedula',
            'cedula' => '8-864-1164',
            'password' => Hash::make('secreto-demo'),
            'role' => 'client',
            'is_active' => true,
            'resides_in_panama' => true,
            'is_employee' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'jugador@example.com',
            'password' => 'secreto-demo',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recaptcha_token']);
    }

    public function test_login_skips_captcha_when_recaptcha_is_disabled_from_site_settings(): void
    {
        config()->set('contest.recaptcha_secret', 'test-secret');
        SiteSetting::set('recaptcha_enabled', '0');

        User::query()->create([
            'name' => 'Jugador Demo',
            'email' => 'jugador@example.com',
            'document_type' => 'cedula',
            'cedula' => '8-864-1164',
            'password' => Hash::make('secreto-demo'),
            'role' => 'client',
            'is_active' => true,
            'resides_in_panama' => true,
            'is_employee' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'jugador@example.com',
            'password' => 'secreto-demo',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Bienvenido.');
    }

    public function test_admin_can_store_recaptcha_keys_in_site_settings_and_public_endpoint_only_exposes_site_key(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $admin = User::query()->create([
            'name' => 'Admin Demo',
            'email' => 'admin@example.com',
            'document_type' => 'cedula',
            'cedula' => '8-000-0001',
            'password' => Hash::make('admin-secret'),
            'role' => 'admin',
            'is_active' => true,
            'resides_in_panama' => true,
            'is_employee' => false,
        ]);

        $response = $this
            ->actingAs($admin)
            ->put('/adminrepus1car/site', [
                'auth_bg_youtube_id' => '',
                'auth_logo_url' => '',
                'header_logo_url' => '',
                'participant_brands' => '',
                'hero_video_url' => '',
                'seo_site_title' => '',
                'seo_meta_description' => '',
                'seo_meta_keywords' => '',
                'seo_og_title' => '',
                'seo_og_description' => '',
                'seo_og_image' => '',
                'terms_and_conditions' => '',
                'contact_email' => '',
                'contact_phone' => '',
                'contact_address' => '',
                'contact_hours' => '',
                'theme_background' => '',
                'theme_surface_low' => '',
                'theme_surface' => '',
                'theme_surface_high' => '',
                'theme_primary' => '',
                'theme_secondary' => '',
                'theme_text_main' => '',
                'theme_outline_variant' => '',
                'show_scanner_debug' => '0',
                'show_auth_ticker' => '1',
                'recaptcha_enabled' => '1',
                'recaptcha_site_key' => 'site-key-v2-demo',
                'recaptcha_secret_key' => 'secret-key-v2-demo',
            ]);

        $response->assertRedirect();

        $this->assertSame('site-key-v2-demo', SiteSetting::get('recaptcha_site_key'));
        $this->assertSame('secret-key-v2-demo', SiteSetting::get('recaptcha_secret_key'));
        $this->assertSame('1', SiteSetting::get('recaptcha_enabled'));

        $publicResponse = $this->getJson('/api/public/settings');

        $publicResponse
            ->assertOk()
            ->assertJsonPath('recaptcha_enabled', true)
            ->assertJsonPath('recaptcha_site_key', 'site-key-v2-demo')
            ->assertJsonMissingPath('recaptcha_secret_key');
    }
}
