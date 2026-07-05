<?php

namespace Tests\Feature;

use App\Models\OnlineStoreOrderClaim;
use Illuminate\Support\Arr;
use App\Models\TournamentMatch;
use App\Models\TournamentPhase;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletMovement;
use App\Support\PromotionRankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OnlineStoreOrderBonusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.magento.base_url' => 'https://magento.test',
            'services.magento.access_token' => 'test-token',
            'services.magento.order_bonus_enabled' => true,
            'services.magento.order_bonus_statuses' => ['processing', 'complete', 'authorized_payment'],
        ]);
    }

    public function test_client_submits_online_store_order_claim_without_receiving_points_immediately(): void
    {
        $this->travelTo('2026-07-05 09:30:00');
        $this->seedFirstRoundOf16Match('2026-07-05 17:00:00');
        $user = $this->createClient('cliente@example.com');
        Sanctum::actingAs($user);
        $this->fakeMagentoOrders([
            $this->magentoOrder(
                entityId: 1001,
                incrementId: '13000001729',
                email: 'cliente@example.com',
                total: 25.00,
                status: 'processing',
                createdAt: '2026-07-05 07:00:00',
            ),
        ]);

        $response = $this->postJson('/api/client/online-orders/verify', [
            'order_number' => '13000001729',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('credited_points', 0);

        $this->assertDatabaseHas('online_store_order_claims', [
            'user_id' => $user->id,
            'increment_id' => '13000001729',
            'status' => 'pending',
            'points_awarded' => 0,
        ]);
        $this->assertSame(0, (int) $user->wallet()->first()?->goals_balance);
        $this->assertSame(0, WalletMovement::query()->where('user_id', $user->id)->count());
    }

    public function test_client_cannot_submit_claim_when_magento_order_is_not_found(): void
    {
        $this->travelTo('2026-07-05 09:30:00');
        $this->seedFirstRoundOf16Match('2026-07-05 17:00:00');
        $user = $this->createClient('cliente@example.com');
        Sanctum::actingAs($user);
        $this->fakeMagentoOrders([]);

        $response = $this->postJson('/api/client/online-orders/verify', [
            'order_number' => '1200000032812',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('order_number');

        $this->assertDatabaseCount('online_store_order_claims', 0);
        $this->assertSame(0, (int) $user->wallet()->first()?->goals_balance);
    }

    public function test_multiple_users_can_submit_claims_for_same_online_store_order_for_admin_review(): void
    {
        $this->travelTo('2026-07-05 09:30:00');
        $this->seedFirstRoundOf16Match('2026-07-05 17:00:00');
        $firstUser = $this->createClient('cliente@example.com');
        $secondUser = $this->createClient('dueno@example.com');
        $this->fakeMagentoOrders([
            $this->magentoOrder(
                entityId: 1010,
                incrementId: '13000001729',
                email: 'dueno@example.com',
                total: 25.00,
                status: 'processing',
                createdAt: '2026-07-05 08:00:00',
            ),
        ]);

        Sanctum::actingAs($firstUser);
        $this->postJson('/api/client/online-orders/verify', [
            'order_number' => '13000001729',
        ])->assertOk();

        Sanctum::actingAs($secondUser);
        $this->postJson('/api/client/online-orders/verify', [
            'order_number' => '13000001729',
        ])->assertOk();

        $this->assertSame(2, OnlineStoreOrderClaim::query()->where('increment_id', '13000001729')->count());
    }

    public function test_online_store_order_claim_stores_only_sanitized_magento_snapshot(): void
    {
        $this->travelTo('2026-07-05 08:30:00');
        $this->seedFirstRoundOf16Match('2026-07-05 17:00:00');
        $user = $this->createClient('cliente@example.com');
        Sanctum::actingAs($user);
        $payload = $this->magentoOrder(
            entityId: 1011,
            incrementId: '13000001733',
            email: 'cliente@example.com',
            total: 25.00,
            status: 'processing',
            createdAt: '2026-07-05 09:00:00',
        );
        $payload['billing_address'] = ['telephone' => '61234567', 'street' => ['Casa privada']];
        $payload['payment'] = ['cc_last4' => '4242'];
        $this->fakeMagentoOrders([$payload]);

        $this->postJson('/api/client/online-orders/verify', [
            'order_number' => '13000001733',
        ])->assertOk();

        $snapshot = OnlineStoreOrderClaim::query()->firstOrFail()->raw_payload;

        $this->assertSame([
            'entity_id',
            'increment_id',
            'customer_email',
            'grand_total',
            'order_currency_code',
            'base_currency_code',
            'status',
            'created_at',
            'store_id',
            'store_name',
        ], array_keys($snapshot));
        $this->assertFalse(Arr::has($snapshot, 'billing_address'));
        $this->assertFalse(Arr::has($snapshot, 'payment'));
    }

    public function test_online_store_order_claim_endpoint_is_rate_limited(): void
    {
        $this->travelTo('2026-07-05 09:30:00');
        $this->seedFirstRoundOf16Match('2026-07-05 10:00:00');
        $user = $this->createClient('cliente@example.com');
        Sanctum::actingAs($user);
        $this->fakeMagentoOrders([
            $this->magentoOrder(
                entityId: 1012,
                incrementId: '13000001734',
                email: 'cliente@example.com',
                total: 25.00,
                status: 'processing',
                createdAt: '2026-07-05 08:00:00',
            ),
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/client/online-orders/verify', [
                'order_number' => '13000001734',
            ])->assertOk();
        }

        $this->postJson('/api/client/online-orders/verify', [
            'order_number' => '13000001734',
        ])->assertStatus(429);
    }

    public function test_admin_can_approve_valid_online_store_order_claim_and_credit_five_points(): void
    {
        $this->travelTo('2026-07-05 09:30:00');
        $this->seedFirstRoundOf16Match('2026-07-05 10:00:00');
        $user = $this->createClient('cliente@example.com');
        $admin = $this->createAdmin();
        Sanctum::actingAs($user);
        $this->fakeMagentoOrders([
            $this->magentoOrder(
                entityId: 1002,
                incrementId: '13000001729',
                email: 'cliente@example.com',
                total: 25.00,
                status: 'authorized_payment',
                createdAt: '2026-07-05 09:00:00',
            ),
        ]);
        $this->postJson('/api/client/online-orders/verify', [
            'order_number' => '13000001729',
        ])->assertOk();

        $claim = OnlineStoreOrderClaim::query()->firstOrFail();
        $response = $this->actingAs($admin)
            ->post(route('admin.online-order-claims.approve', $claim), [
                'review_notes' => 'Orden validada en Magento.',
            ]);

        $response->assertRedirect(route('admin.online-order-claims'));
        $this->assertDatabaseHas('online_store_order_claims', [
            'id' => $claim->id,
            'status' => 'approved',
            'reviewed_by_user_id' => $admin->id,
            'points_awarded' => 5,
        ]);
        $this->assertDatabaseHas('online_store_orders', [
            'user_id' => $user->id,
            'increment_id' => '13000001729',
            'points_awarded' => 5,
        ]);
        $this->assertSame(5, (int) $user->wallet()->first()?->goals_balance);

        $movement = WalletMovement::query()->where('user_id', $user->id)->first();
        $this->assertSame('online_store_purchase_bonus', $movement?->type);
        $this->assertSame(5, $movement?->goals_delta);
    }

    public function test_admin_can_reject_online_store_order_claim_without_crediting_points(): void
    {
        $this->travelTo('2026-07-05 09:30:00');
        $this->seedFirstRoundOf16Match('2026-07-05 10:00:00');
        $user = $this->createClient('cliente@example.com');
        $admin = $this->createAdmin();
        Sanctum::actingAs($user);
        $this->fakeMagentoOrders([
            $this->magentoOrder(
                entityId: 1003,
                incrementId: '13000001730',
                email: 'otro@example.com',
                total: 25.00,
                status: 'processing',
                createdAt: '2026-07-05 09:00:00',
            ),
        ]);
        $this->postJson('/api/client/online-orders/verify', [
            'order_number' => '13000001730',
        ])->assertOk();

        $claim = OnlineStoreOrderClaim::query()->firstOrFail();
        $response = $this->actingAs($admin)
            ->post(route('admin.online-order-claims.reject', $claim), [
                'review_notes' => 'El correo Magento no coincide.',
            ]);

        $response->assertRedirect(route('admin.online-order-claims'));
        $this->assertDatabaseHas('online_store_order_claims', [
            'id' => $claim->id,
            'status' => 'rejected',
            'reviewed_by_user_id' => $admin->id,
            'points_awarded' => 0,
        ]);
        $this->assertSame(0, (int) $user->wallet()->first()?->goals_balance);
        $this->assertSame(0, WalletMovement::query()->where('user_id', $user->id)->count());
    }

    public function test_claim_cannot_be_submitted_after_promo_window_ends(): void
    {
        $this->travelTo('2026-07-07 05:00:00');
        $this->seedFirstRoundOf16Match('2026-07-05 10:00:00');
        $user = $this->createClient('cliente@example.com');
        Sanctum::actingAs($user);

        $this->postJson('/api/client/online-orders/verify', [
            'order_number' => '13000001729',
        ])->assertStatus(422);

        $this->assertDatabaseCount('online_store_order_claims', 0);
    }

    public function test_admin_approval_revalidates_minimum_total_before_crediting_points(): void
    {
        $this->travelTo('2026-07-05 09:30:00');
        $this->seedFirstRoundOf16Match('2026-07-05 10:00:00');
        $user = $this->createClient('cliente@example.com');
        $admin = $this->createAdmin();
        Sanctum::actingAs($user);
        $this->fakeMagentoOrders([
            $this->magentoOrder(
                entityId: 1004,
                incrementId: '13000001731',
                email: 'cliente@example.com',
                total: 24.99,
                status: 'processing',
                createdAt: '2026-07-05 09:00:00',
            ),
        ]);
        $this->postJson('/api/client/online-orders/verify', [
            'order_number' => '13000001731',
        ])->assertOk();

        $claim = OnlineStoreOrderClaim::query()->firstOrFail();
        $response = $this->actingAs($admin)
            ->post(route('admin.online-order-claims.approve', $claim));

        $response->assertSessionHasErrors();
        $this->assertDatabaseHas('online_store_order_claims', [
            'id' => $claim->id,
            'status' => 'pending',
            'points_awarded' => 0,
        ]);
        $this->assertSame(0, (int) $user->wallet()->first()?->goals_balance);
    }

    public function test_online_store_bonus_counts_only_in_knockout_ranking_after_admin_approval(): void
    {
        $this->travelTo('2026-07-05 09:30:00');
        $groupPhase = TournamentPhase::query()->where('slug', 'fase-grupos')->firstOrFail();
        $knockoutPhase = $this->activatePhase('octavos');
        $this->seedFirstRoundOf16Match('2026-07-05 10:00:00', $knockoutPhase);
        $user = $this->createClient('cliente@example.com');
        $admin = $this->createAdmin();
        Sanctum::actingAs($user);
        $this->fakeMagentoOrders([
            $this->magentoOrder(
                entityId: 1005,
                incrementId: '13000001732',
                email: 'cliente@example.com',
                total: 30.00,
                status: 'complete',
                createdAt: '2026-07-05 09:00:00',
            ),
        ]);
        $this->postJson('/api/client/online-orders/verify', [
            'order_number' => '13000001732',
        ])->assertOk();
        $claim = OnlineStoreOrderClaim::query()->firstOrFail();
        $this->actingAs($admin)->post(route('admin.online-order-claims.approve', $claim))->assertRedirect();

        $groupRow = app(PromotionRankingService::class)
            ->fullRankedLeaderboard($groupPhase->id)
            ->firstWhere('user_id', $user->id);
        $knockoutRow = app(PromotionRankingService::class)
            ->fullRankedLeaderboard($knockoutPhase->id)
            ->firstWhere('user_id', $user->id);

        $this->assertSame(0.0, $groupRow['total_points']);
        $this->assertSame(5.0, $knockoutRow['total_points']);
        $this->assertSame(5.0, $knockoutRow['online_store_points']);
    }

    public function test_admin_can_credit_valid_whatsapp_online_order_with_mismatched_magento_email(): void
    {
        $this->travelTo('2026-07-05 10:30:00');
        $this->seedFirstRoundOf16Match('2026-07-05 17:00:00');
        $user = $this->createClient('cliente-app@example.com');
        $admin = $this->createAdmin();
        $this->fakeMagentoOrders([
            $this->magentoOrder(
                entityId: 2001,
                incrementId: '10000000193',
                email: 'cliente-magento@example.com',
                total: 25.00,
                status: 'authorized_payment',
                createdAt: '2026-07-05 08:00:00',
            ),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.online-order-claims.whatsapp.store'), [
            'cedula' => $user->cedula,
            'order_number' => '10000000193',
            'source_reported_at' => '2026-07-05T07:30',
            'review_notes' => 'Cliente reporto la compra por WhatsApp al 68982167.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('online_store_order_claims', [
            'user_id' => $user->id,
            'increment_id' => '10000000193',
            'customer_email' => 'cliente-magento@example.com',
            'status' => 'approved',
            'source' => 'admin_whatsapp',
            'points_awarded' => 5,
            'created_by_user_id' => $admin->id,
            'reviewed_by_user_id' => $admin->id,
        ]);
        $this->assertSame(5, (int) $user->wallet()->first()?->goals_balance);

        $movement = WalletMovement::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('admin_whatsapp', $movement->meta['source'] ?? null);
        $this->assertSame($admin->id, $movement->meta['admin_user_id'] ?? null);
        $this->assertSame('10000000193', $movement->meta['order']['increment_id'] ?? null);
    }

    public function test_admin_whatsapp_credit_requires_existing_client_document(): void
    {
        $this->travelTo('2026-07-05 08:30:00');
        $this->seedFirstRoundOf16Match('2026-07-05 10:00:00');
        $admin = $this->createAdmin();
        $this->fakeMagentoOrders([]);

        $response = $this->actingAs($admin)->from(route('admin.online-order-claims'))->post(route('admin.online-order-claims.whatsapp.store'), [
            'cedula' => '8-000-000',
            'order_number' => '10000000193',
            'source_reported_at' => '2026-07-05T08:00',
            'review_notes' => 'Reporte WhatsApp.',
        ]);

        $response->assertRedirect(route('admin.online-order-claims'));
        $response->assertSessionHasErrors('cedula');
        $this->assertDatabaseCount('online_store_order_claims', 0);
    }

    public function test_admin_whatsapp_credit_rejects_disqualified_user(): void
    {
        $this->travelTo('2026-07-05 08:30:00');
        $this->seedFirstRoundOf16Match('2026-07-05 10:00:00');
        $user = $this->createClient('cliente@example.com');
        $user->forceFill(['disqualified_at' => now()])->save();
        $admin = $this->createAdmin();
        $this->fakeMagentoOrders([
            $this->magentoOrder(
                entityId: 2002,
                incrementId: '10000000194',
                email: 'cliente@example.com',
                total: 25.00,
                status: 'processing',
                createdAt: '2026-07-05 07:00:00',
            ),
        ]);

        $response = $this->actingAs($admin)->from(route('admin.online-order-claims'))->post(route('admin.online-order-claims.whatsapp.store'), [
            'cedula' => $user->cedula,
            'order_number' => '10000000194',
            'source_reported_at' => '2026-07-05T07:30',
            'review_notes' => 'Reporte WhatsApp.',
        ]);

        $response->assertRedirect(route('admin.online-order-claims'));
        $response->assertSessionHasErrors('cedula');
        $this->assertSame(0, (int) $user->wallet()->first()?->goals_balance);
    }

    public function test_admin_whatsapp_credit_rejects_report_after_promo_window_ends(): void
    {
        $this->travelTo('2026-07-05 10:30:00');
        $this->seedFirstRoundOf16Match('2026-07-05 17:00:00');
        $user = $this->createClient('cliente@example.com');
        $admin = $this->createAdmin();
        $this->fakeMagentoOrders([
            $this->magentoOrder(
                entityId: 2003,
                incrementId: '10000000195',
                email: 'cliente@example.com',
                total: 25.00,
                status: 'processing',
                createdAt: '2026-07-05 09:00:00',
            ),
        ]);

        $response = $this->actingAs($admin)->from(route('admin.online-order-claims'))->post(route('admin.online-order-claims.whatsapp.store'), [
            'cedula' => $user->cedula,
            'order_number' => '10000000195',
            'source_reported_at' => '2026-07-07T12:05',
            'review_notes' => 'Reporte WhatsApp.',
        ]);

        $response->assertRedirect(route('admin.online-order-claims'));
        $response->assertSessionHasErrors('source_reported_at');
        $this->assertDatabaseCount('online_store_order_claims', 0);
    }

    public function test_admin_whatsapp_credit_does_not_duplicate_an_already_credited_order(): void
    {
        $this->travelTo('2026-07-05 08:30:00');
        $this->seedFirstRoundOf16Match('2026-07-05 17:00:00');
        $user = $this->createClient('cliente@example.com');
        $admin = $this->createAdmin();
        $this->fakeMagentoOrders([
            $this->magentoOrder(
                entityId: 2004,
                incrementId: '10000000196',
                email: 'cliente@example.com',
                total: 25.00,
                status: 'processing',
                createdAt: '2026-07-05 08:00:00',
            ),
        ]);

        $payload = [
            'cedula' => $user->cedula,
            'order_number' => '10000000196',
            'source_reported_at' => '2026-07-05T08:00',
            'review_notes' => 'Reporte WhatsApp.',
        ];

        $this->actingAs($admin)->post(route('admin.online-order-claims.whatsapp.store'), $payload)->assertRedirect();
        $response = $this->actingAs($admin)->from(route('admin.online-order-claims'))->post(route('admin.online-order-claims.whatsapp.store'), $payload);

        $response->assertRedirect(route('admin.online-order-claims'));
        $response->assertSessionHasErrors('claim');
        $this->assertSame(1, WalletMovement::query()->where('user_id', $user->id)->count());
        $this->assertSame(5, (int) $user->wallet()->first()?->goals_balance);
    }

    private function createClient(string $email): User
    {
        $user = User::query()->create([
            'name' => 'Cliente Online',
            'email' => $email,
            'cedula' => fake()->unique()->numerify('8-###-####'),
            'document_type' => 'cedula',
            'password' => bcrypt('secret'),
            'role' => 'client',
            'phone' => '+50761234567',
            'avatar_path' => 'avatars/test.jpg',
            'is_active' => true,
            'birthdate' => now()->subYears(25)->toDateString(),
            'resides_in_panama' => true,
            'accepted_terms_at' => now(),
            'registration_completed_at' => now(),
            'group_stage_goal_prediction' => 120,
        ]);

        Wallet::query()->create([
            'user_id' => $user->id,
            'goals_balance' => 0,
            'shots_balance' => 0,
            'lifetime_goals_earned' => 0,
            'lifetime_shots_earned' => 0,
        ]);

        return $user;
    }

    private function createAdmin(): User
    {
        return User::query()->create([
            'name' => 'Admin Online',
            'email' => fake()->unique()->safeEmail(),
            'cedula' => fake()->unique()->numerify('8-###-####'),
            'document_type' => 'cedula',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'phone' => '+50761234568',
            'avatar_path' => 'avatars/admin.jpg',
            'is_active' => true,
            'birthdate' => now()->subYears(35)->toDateString(),
            'resides_in_panama' => true,
            'accepted_terms_at' => now(),
            'registration_completed_at' => now(),
        ]);
    }

    private function activatePhase(string $slug): TournamentPhase
    {
        TournamentPhase::query()->where('slug', 'fase-grupos')->update(['is_active' => false]);

        $phase = TournamentPhase::query()->where('slug', $slug)->firstOrFail();
        $phase->update([
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(4),
        ]);

        return $phase->fresh();
    }

    private function seedFirstRoundOf16Match(string $kickoffAt, ?TournamentPhase $phase = null): TournamentMatch
    {
        $phase ??= TournamentPhase::query()->where('slug', 'octavos')->firstOrFail();
        $phase->update(['is_active' => true]);
        $octavosPhaseIds = TournamentPhase::query()->where('slug', 'octavos')->pluck('id');
        TournamentMatch::query()->whereIn('phase_id', $octavosPhaseIds)->delete();

        return TournamentMatch::query()->create([
            'phase_id' => $phase->id,
            'match_number' => 101,
            'round_label' => 'R16',
            'stage_label' => 'Knockout',
            'home_team_id' => $this->insertTeam('Local Octavos', 'LOC'),
            'away_team_id' => $this->insertTeam('Visita Octavos', 'VIS'),
            'favorite_side' => 'home',
            'kickoff_at' => $kickoffAt,
            'status' => 'scheduled',
        ]);
    }

    private function insertTeam(string $name, string $code): int
    {
        return (int) \DB::table('teams')->insertGetId([
            'name' => $name,
            'code' => $code,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function fakeMagentoOrders(array $orders): void
    {
        Http::fake([
            'magento.test/*' => Http::response(['items' => $orders], 200),
        ]);
    }

    private function magentoOrder(
        int $entityId,
        string $incrementId,
        string $email,
        float $total,
        string $status,
        string $createdAt,
    ): array {
        return [
            'entity_id' => $entityId,
            'increment_id' => $incrementId,
            'customer_email' => $email,
            'grand_total' => $total,
            'order_currency_code' => 'USD',
            'status' => $status,
            'created_at' => $createdAt,
        ];
    }
}
