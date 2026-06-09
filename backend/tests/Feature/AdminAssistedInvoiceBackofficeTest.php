<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\FraudFlag;
use App\Models\InvoiceGoalSetting;
use App\Models\RegisteredInvoice;
use App\Models\Branch;
use App\Models\TournamentPhase;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletMovement;
use App\Support\ContestInvoiceVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AdminAssistedInvoiceBackofficeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_and_edit_participants(): void
    {
        $admin = $this->createAdmin();
        $branch = Branch::query()->create([
            'name' => 'Sucursal Central',
            'code' => 'CENTRAL',
            'address' => 'Direccion prueba',
            'phone' => '555-0000',
            'is_active' => true,
        ]);
        $player = $this->createClient('+50760001111');

        $listResponse = $this->actingAs($admin)->get('/adminrepus1car/users?query='.$player->cedula);
        $listResponse->assertOk();
        $listResponse->assertSee($player->cedula, false);

        $editResponse = $this->actingAs($admin)->get("/adminrepus1car/users/{$player->id}/edit");
        $editResponse->assertOk();
        $editResponse->assertSee('Editar participante');

        $response = $this->actingAs($admin)->put("/adminrepus1car/users/{$player->id}", [
            'name' => 'Cliente Editado',
            'cedula' => '8-123-4567',
            'document_type' => 'cedula',
            'email' => 'editado@example.com',
            'phone' => '+50769990000',
            'branch_id' => $branch->id,
            'birthdate' => '1990-01-15',
            'resides_in_panama' => 1,
            'is_employee' => 0,
            'is_active' => 1,
            'password' => 'NuevaClave123',
            'disqualify_user' => 0,
            'disqualification_reason' => null,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Participante actualizado.');

        $player->refresh();
        $this->assertSame('Cliente Editado', $player->name);
        $this->assertSame('8-123-4567', $player->cedula);
        $this->assertSame('editado@example.com', $player->email);
        $this->assertSame((string) $branch->id, (string) $player->branch_id);
        $this->assertTrue($player->is_active);
    }

    public function test_admin_can_view_participant_audit_trail(): void
    {
        $admin = $this->createAdmin();
        $player = $this->createClient('+50760002222');

        $this->actingAs($admin)->put("/adminrepus1car/users/{$player->id}", [
            'name' => 'Cliente Auditoria',
            'cedula' => '8-765-4321',
            'document_type' => 'cedula',
            'email' => 'auditoria@example.com',
            'phone' => '+50769990001',
            'branch_id' => null,
            'birthdate' => '1992-03-10',
            'resides_in_panama' => 1,
            'is_employee' => 0,
            'is_active' => 1,
            'password' => '',
            'disqualify_user' => 0,
            'disqualification_reason' => null,
        ])->assertRedirect();

        $auditResponse = $this->actingAs($admin)->get("/adminrepus1car/users/{$player->id}/audit");
        $auditResponse->assertOk();
        $auditResponse->assertSee('Auditoria del participante');
        $auditResponse->assertSee('user.profile.updated');
        $auditResponse->assertSee('cedula');
        $auditResponse->assertSee('8-765-4321', false);
    }

    public function test_admin_views_whatsapp_links_in_fraud_queue_and_participant_detail(): void
    {
        $admin = $this->createAdmin();
        $player = $this->createClient('+50761234567');

        FraudFlag::query()->create([
            'user_id' => $player->id,
            'flag_type' => 'invalid_cufe_format',
            'source' => 'system',
            'severity' => 'high',
            'status' => 'open',
            'title' => 'Intento de registro sin CUFE valido',
            'description' => 'No se pudo leer el CUFE.',
        ]);

        $fraudResponse = $this->actingAs($admin)->get('/adminrepus1car/fraud');
        $fraudResponse->assertOk();
        $fraudResponse->assertSee('https://wa.me/50761234567', false);

        $detailResponse = $this->actingAs($admin)->get("/adminrepus1car/player-points/{$player->id}");
        $detailResponse->assertOk();
        $detailResponse->assertSee('https://wa.me/50761234567', false);
        $detailResponse->assertSee('Registrar factura asistida');
    }

    public function test_admin_can_register_assisted_invoice_from_fraud_case_with_full_traceability(): void
    {
        $admin = $this->createAdmin();
        $player = $this->createClient('+50769876543');

        $campaign = Campaign::query()->create([
            'name' => 'Promo activa',
            'description' => 'Campana de pruebas',
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'invoice_min_amount_for_shot' => 25,
            'amount_per_point' => 25,
            'points_per_block' => 1,
            'daily_max_points' => 100,
            'daily_max_invoices' => 100,
            'coupon_ttl_hours' => 72,
            'games_enabled' => false,
            'major_prizes_enabled' => false,
            'invoice_scan_enabled' => true,
            'redemption_enabled' => false,
        ]);

        InvoiceGoalSetting::query()->create([
            'is_enabled' => true,
            'goal_value' => 1,
            'min_purchase_amount' => 25,
            'max_invoice_age_days' => 2,
            'one_invoice_per_day' => false,
            'validation_mode' => 'api',
        ]);

        TournamentPhase::query()->firstOrCreate(
            ['slug' => 'fase-grupos'],
            [
                'name' => 'Fase de Grupos',
                'stage_order' => 1,
                'starts_at' => now()->subDays(3),
                'ends_at' => now()->addDays(3),
                'exact_score_points' => 3,
                'outcome_points' => 1,
                'reset_phase_table' => false,
                'is_active' => true,
            ],
        );

        $flag = FraudFlag::query()->create([
            'user_id' => $player->id,
            'flag_type' => 'dgi_invoice_resolution_failed',
            'source' => 'system',
            'severity' => 'critical',
            'status' => 'open',
            'title' => 'CUFE no confirmado por DGI',
            'description' => 'El cliente reporto error durante el registro.',
        ]);

        $verifier = Mockery::mock(ContestInvoiceVerifier::class);
        $verifier->shouldReceive('resolve')
            ->once()
            ->with('FE01200000032812-2-249262-ABC123456789XYZ')
            ->andReturn([
                'cufe' => 'FE01200000032812-2-249262-ABC123456789XYZ',
                'invoice_number' => 'FAC-9001',
                'purchase_amount' => 55.40,
                'issued_at' => now('America/Panama')->subHours(2),
                'issuer_ruc' => '',
                'issuer_name' => 'SUPER CARNES',
                'payload' => ['status' => 'approved'],
            ]);
        $verifier->shouldReceive('verify')
            ->once()
            ->andReturn([
                'status' => 'approved',
                'notes' => 'Factura validada contra DGI.',
                'canonical_cufe' => 'FE01200000032812-2-249262-ABC123456789XYZ',
                'payload' => ['status' => 'approved'],
            ]);
        $this->app->instance(ContestInvoiceVerifier::class, $verifier);

        $response = $this->actingAs($admin)->post("/adminrepus1car/users/{$player->id}/assisted-invoices", [
            'qr_raw_text' => 'FE01200000032812-2-249262-ABC123456789XYZ',
            'branch_id' => null,
            'fraud_flag_id' => $flag->id,
            'assistance_notes' => 'Cliente envio CUFE por WhatsApp y se le apoyo manualmente.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', function (string $status): bool {
            return str_contains($status, 'Factura asistida aprobada')
                && str_contains($status, 'Puntos acreditados: 1');
        });

        $invoice = RegisteredInvoice::query()->first();
        $this->assertNotNull($invoice);
        $this->assertSame($player->id, $invoice->user_id);
        $this->assertSame('admin_assisted', $invoice->registration_source);
        $this->assertSame($admin->id, $invoice->registered_by_user_id);
        $this->assertSame($flag->id, $invoice->assisted_by_fraud_flag_id);
        $this->assertSame('approved', $invoice->validation_status);
        $this->assertSame('Cliente envio CUFE por WhatsApp y se le apoyo manualmente.', $invoice->assistance_notes);

        $flag->refresh();
        $this->assertSame($invoice->id, $flag->registered_invoice_id);
        $this->assertSame('resolved', $flag->status);
        $this->assertSame($admin->id, $flag->reviewed_by_user_id);
        $this->assertStringContainsString('Factura asistida registrada', (string) $flag->resolution_notes);

        $wallet = Wallet::query()->where('user_id', $player->id)->first();
        $this->assertNotNull($wallet);
        $this->assertSame(1, $wallet->goals_balance);

        $movement = WalletMovement::query()->where('user_id', $player->id)->first();
        $this->assertNotNull($movement);
        $this->assertSame('admin_assisted', data_get($movement->meta, 'registration_source'));
        $this->assertSame($admin->id, data_get($movement->meta, 'registered_by_user_id'));
    }

    private function createAdmin(): User
    {
        return User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'cedula' => 'ADMIN-1',
            'document_type' => 'passport',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    private function createClient(string $phone): User
    {
        return User::query()->create([
            'name' => 'Cliente Soportado',
            'email' => fake()->unique()->safeEmail(),
            'cedula' => fake()->unique()->numerify('8-###-####'),
            'document_type' => 'cedula',
            'password' => bcrypt('secret'),
            'role' => 'client',
            'phone' => $phone,
            'is_active' => true,
            'accepted_terms_at' => now(),
            'registration_completed_at' => now(),
        ]);
    }
}
