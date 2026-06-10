<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_auth_creates_an_incomplete_user_with_wallet_and_marks_completion_required(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'aud' => 'google-client-id',
                'iss' => 'https://accounts.google.com',
                'email_verified' => 'true',
                'email' => 'jugador@example.com',
                'name' => 'Jugador Google',
                'sub' => 'google-sub-123',
            ]),
        ]);

        config()->set('contest.allow_google_auth', true);
        config()->set('services.google.client_id', 'google-client-id');

        $response = $this->postJson('/api/auth/google', [
            'credential' => 'google-jwt',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('requires_registration_completion', true)
            ->assertJsonPath('user.email', 'jugador@example.com')
            ->assertJsonPath('user.registration_completed_at', null)
            ->assertJsonPath('user.wallet.goals_balance', 0);

        $this->assertDatabaseHas('users', [
            'email' => 'jugador@example.com',
            'google_id' => 'google-sub-123',
            'registration_completed_at' => null,
        ]);
        $this->assertDatabaseCount('wallets', 1);
    }

    public function test_incomplete_google_user_cannot_access_protected_client_endpoints(): void
    {
        $user = User::query()->create([
            'name' => 'Google Pending',
            'email' => 'pending@example.com',
            'google_id' => 'google-sub-pending',
            'document_type' => 'passport',
            'cedula' => 'GOOGLE-PENDING',
            'password' => bcrypt('temporary-secret'),
            'role' => 'client',
            'is_active' => true,
            'resides_in_panama' => false,
            'is_employee' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/client/bootstrap');

        $response
            ->assertStatus(403)
            ->assertJsonPath('message', 'Debes completar tu registro para continuar.');
    }

    public function test_google_user_can_complete_registration_with_required_contest_fields(): void
    {
        Storage::fake('public');

        $user = User::query()->create([
            'name' => 'Google Pending',
            'email' => 'pending@example.com',
            'google_id' => 'google-sub-pending',
            'document_type' => 'passport',
            'cedula' => 'GOOGLE-PENDING',
            'password' => bcrypt('temporary-secret'),
            'role' => 'client',
            'is_active' => true,
            'resides_in_panama' => false,
            'is_employee' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->post('/api/auth/google/complete', [
            'full_name' => 'Ana Google',
            'document_type' => 'cedula',
            'cedula' => '8-864-1164',
            'phone' => '+50761234567',
            'birthdate' => now('America/Panama')->subYears(20)->toDateString(),
            'resides_in_panama' => '1',
            'is_employee' => '0',
            'accepted_terms' => '1',
            'group_stage_goal_prediction' => '101',
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('requires_registration_completion', false)
            ->assertJsonPath('user.full_name', 'Ana Google')
            ->assertJsonPath('user.document_type', 'cedula')
            ->assertJsonPath('user.cedula', '8-864-1164')
            ->assertJsonPath('user.phone', '+50761234567');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Ana Google',
            'document_type' => 'cedula',
            'cedula' => '8-864-1164',
            'phone' => '+50761234567',
            'resides_in_panama' => true,
            'is_employee' => false,
        ]);

        $user->refresh();
        $this->assertNotNull($user->accepted_terms_at);
        $this->assertNotNull($user->registration_completed_at);
        $this->assertNotNull($user->avatar_path);
    }

    public function test_google_registration_avatar_is_optimized_to_at_most_500_kb(): void
    {
        Storage::fake('public');

        $user = User::query()->create([
            'name' => 'Google Pending',
            'email' => 'pending@example.com',
            'google_id' => 'google-sub-pending',
            'document_type' => 'passport',
            'cedula' => 'GOOGLE-PENDING',
            'password' => bcrypt('temporary-secret'),
            'role' => 'client',
            'is_active' => true,
            'resides_in_panama' => false,
            'is_employee' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->post('/api/auth/google/complete', [
            'full_name' => 'Ana Google',
            'document_type' => 'cedula',
            'cedula' => '8-864-1164',
            'phone' => '+50761234567',
            'birthdate' => now('America/Panama')->subYears(20)->toDateString(),
            'resides_in_panama' => '1',
            'is_employee' => '0',
            'accepted_terms' => '1',
            'group_stage_goal_prediction' => '101',
            'avatar' => $this->makeLargeAvatarUpload(),
        ]);

        $response->assertOk();

        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
        $this->assertLessThanOrEqual(500 * 1024, Storage::disk('public')->size($user->avatar_path));
    }

    public function test_registration_is_allowed_even_after_the_old_deadline_passes(): void
    {
        Storage::fake('public');

        config()->set('contest.registration_deadline', now('America/Panama')->subDay()->toDateTimeString());

        $branch = Branch::query()->create([
            'name' => 'Sucursal Central',
            'code' => 'SC',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/register', [
            'full_name' => 'Participante Tardio',
            'document_type' => 'cedula',
            'cedula' => '8-888-8888',
            'email' => 'tardio@example.com',
            'phone' => '+50761234567',
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
            'birthdate' => now('America/Panama')->subYears(20)->toDateString(),
            'resides_in_panama' => '1',
            'is_employee' => '0',
            'accepted_terms' => '1',
            'group_stage_goal_prediction' => '120',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'branch_id' => $branch->id,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.email', 'tardio@example.com');

        $user = User::query()->where('email', 'tardio@example.com')->firstOrFail();
        $this->assertNotNull($user->registration_completed_at);
    }

    public function test_profile_avatar_update_is_optimized_to_at_most_500_kb(): void
    {
        Storage::fake('public');

        $user = User::query()->create([
            'name' => 'Jugador Activo',
            'email' => 'jugador@example.com',
            'document_type' => 'cedula',
            'cedula' => '8-864-1164',
            'password' => bcrypt('temporary-secret'),
            'role' => 'client',
            'is_active' => true,
            'resides_in_panama' => true,
            'is_employee' => false,
            'phone' => '+50761234567',
            'accepted_terms_at' => now(),
            'registration_completed_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->post('/api/auth/profile', [
            'email' => 'jugador@example.com',
            'phone' => '+50761234567',
            'avatar' => $this->makeLargeAvatarUpload('profile-avatar.png'),
        ]);

        $response->assertOk();

        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
        $this->assertLessThanOrEqual(500 * 1024, Storage::disk('public')->size($user->avatar_path));
    }

    private function makeLargeAvatarUpload(string $filename = 'avatar.png'): UploadedFile
    {
        $width = 2200;
        $height = 2200;
        $image = imagecreatetruecolor($width, $height);

        if (! $image) {
            $this->fail('No se pudo crear la imagen de prueba.');
        }

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                imagesetpixel($image, $x, $y, imagecolorallocate($image, ($x * 7 + $y * 3) % 255, ($x * 11 + $y * 5) % 255, ($x * 13 + $y * 17) % 255));
            }
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'avatar-test-');

        if ($tempPath === false) {
            imagedestroy($image);
            $this->fail('No se pudo crear un archivo temporal para la imagen de prueba.');
        }

        imagepng($image, $tempPath, 0);
        imagedestroy($image);

        return new UploadedFile($tempPath, $filename, 'image/png', null, true);
    }
}
