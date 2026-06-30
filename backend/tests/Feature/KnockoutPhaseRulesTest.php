<?php

namespace Tests\Feature;

use App\Models\MatchPrediction;
use App\Models\PhasePrize;
use App\Models\PromoWinner;
use App\Models\RegisteredInvoice;
use App\Models\TournamentMatch;
use App\Models\TournamentPhase;
use App\Models\User;
use App\Models\Wallet;
use App\Support\LiveScoreApiClient;
use App\Support\LiveScoreSyncService;
use App\Support\PromotionRankingService;
use App\Support\TournamentScoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KnockoutPhaseRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_knockout_leaderboard_keeps_group_stage_winners_visible(): void
    {
        $groupPhase = TournamentPhase::query()->where('slug', 'fase-grupos')->firstOrFail();
        $knockoutPhase = $this->activatePhase('final');

        $priorWinner = $this->createClient('Ganador de Grupos', 'grupos@example.com', '8-111-0001');
        $newPlayer = $this->createClient('Nuevo Finalista', 'nuevo@example.com', '8-111-0002');

        PromoWinner::query()->create([
            'phase_id' => $groupPhase->id,
            'user_id' => $priorWinner->id,
            'leaderboard_position' => 1,
            'total_points' => 100,
            'exact_hits' => 10,
            'invoice_count' => 0,
            'invoice_total_amount' => 0,
            'selection_reason' => 'rank',
            'status' => 'selected',
            'selected_at' => now(),
        ]);

        $match = $this->createMatch($knockoutPhase);
        $this->createPrediction($match, $priorWinner, 12);
        $this->createPrediction($match, $newPlayer, 8);

        $leaderboard = app(PromotionRankingService::class)->leaderboardForPhase($knockoutPhase->id, 10);

        $this->assertSame(
            [$priorWinner->id, $newPlayer->id],
            $leaderboard->pluck('user_id')->all(),
        );
    }

    public function test_generating_knockout_winners_skips_group_stage_winners(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'cedula' => 'ADMIN-1',
            'document_type' => 'passport',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $groupPhase = TournamentPhase::query()->where('slug', 'fase-grupos')->firstOrFail();
        $knockoutPhase = $this->activatePhase('final');

        PhasePrize::query()->create([
            'phase_id' => $knockoutPhase->id,
            'ranking_from' => 1,
            'ranking_to' => 1,
            'football_role' => 'Ganador Bono',
            'prize_title' => 'Bono Super Carnes USD 200',
            'prize_type' => 'bono_200',
            'stock' => 1,
        ]);

        $priorWinner = $this->createClient('Ganador de Grupos', 'grupos@example.com', '8-111-0001');
        $eligiblePlayer = $this->createClient('Finalista Elegible', 'elegible@example.com', '8-111-0002');

        PromoWinner::query()->create([
            'phase_id' => $groupPhase->id,
            'user_id' => $priorWinner->id,
            'leaderboard_position' => 1,
            'total_points' => 100,
            'exact_hits' => 10,
            'invoice_count' => 0,
            'invoice_total_amount' => 0,
            'selection_reason' => 'rank',
            'status' => 'selected',
            'selected_at' => now(),
        ]);

        $match = $this->createMatch($knockoutPhase);
        $this->createPrediction($match, $priorWinner, 12);
        $this->createPrediction($match, $eligiblePlayer, 8);

        $response = $this->actingAs($admin)->post('/adminrepus1car/winners/generate', [
            'phase_id' => $knockoutPhase->id,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseMissing('promo_winners', [
            'phase_id' => $knockoutPhase->id,
            'user_id' => $priorWinner->id,
        ]);
        $this->assertDatabaseHas('promo_winners', [
            'phase_id' => $knockoutPhase->id,
            'user_id' => $eligiblePlayer->id,
            'leaderboard_position' => 2,
            'selection_reason' => 'rank',
            'status' => 'selected',
        ]);
    }

    public function test_knockout_leaderboard_ignores_invoice_points_even_inside_knockout_window(): void
    {
        $finalPhase = $this->activatePhase('final');
        $match = $this->createMatch($finalPhase);

        $predictionPlayer = $this->createClient('Acierta Finales', 'acierta@example.com', '8-111-0010');
        $invoicePlayer = $this->createClient('Factura Finales', 'factura@example.com', '8-111-0011');

        $this->createPrediction($match, $predictionPlayer, 5);
        $this->createApprovedInvoice($invoicePlayer, $finalPhase, 100);

        $leaderboard = app(PromotionRankingService::class)->leaderboardForPhase($finalPhase->id, 10);
        $predictionRow = $leaderboard->firstWhere('user_id', $predictionPlayer->id);
        $invoiceRow = $leaderboard->firstWhere('user_id', $invoicePlayer->id);

        $this->assertSame(5.0, $predictionRow['total_points']);
        $this->assertSame(0.0, $invoiceRow['invoice_points']);
        $this->assertSame(0.0, $invoiceRow['total_points']);
        $this->assertSame([$predictionPlayer->id, $invoicePlayer->id], $leaderboard->pluck('user_id')->all());
    }

    public function test_admin_cannot_generate_knockout_winners_before_final_phase(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin-octavos@example.com',
            'cedula' => 'ADMIN-OCT',
            'document_type' => 'passport',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $roundOf16 = $this->activatePhase('octavos');
        $match = $this->createMatch($roundOf16);
        $player = $this->createClient('Prematuro', 'prematuro@example.com', '8-111-0012');
        $this->createPrediction($match, $player, 5);

        PhasePrize::query()->create([
            'phase_id' => $roundOf16->id,
            'ranking_from' => 1,
            'ranking_to' => 1,
            'football_role' => 'Ganador Bono',
            'prize_title' => 'Bono Super Carnes USD 200',
            'prize_type' => 'bono_200',
            'stock' => 1,
        ]);

        $response = $this->actingAs($admin)->post('/adminrepus1car/winners/generate', [
            'phase_id' => $roundOf16->id,
        ]);

        $response->assertSessionHasErrors('phase');
        $this->assertDatabaseCount('promo_winners', 0);
    }

    public function test_tie_context_checks_candidates_beyond_winner_cutoff(): void
    {
        $finalPhase = $this->activatePhase('final');
        $match = $this->createMatch($finalPhase);

        PhasePrize::query()->create([
            'phase_id' => $finalPhase->id,
            'ranking_from' => 1,
            'ranking_to' => 20,
            'football_role' => 'Ganador Bono',
            'prize_title' => 'Bono Super Carnes USD 200',
            'prize_type' => 'bono_200',
            'stock' => 20,
        ]);

        for ($index = 1; $index <= 21; $index++) {
            $player = $this->createClient(
                'Empatado '.$index,
                'empatado'.$index.'@example.com',
                '8-222-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            );
            $player->update([
                'registration_completed_at' => '2026-06-01 10:00:00',
                'registration_order_key' => 'same-order-key',
            ]);
            $this->createPrediction($match, $player, 5);
        }

        $tieContext = app(PromotionRankingService::class)->tieContextForPhase($finalPhase->id, 20);

        $this->assertTrue($tieContext['requires_draw']);
        $this->assertSame(20, $tieContext['remaining_slots']);
        $this->assertCount(21, $tieContext['tied_candidates']);
    }

    public function test_generate_winners_blocks_when_cutoff_has_unresolved_exact_tie(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin-tie@example.com',
            'cedula' => 'ADMIN-TIE',
            'document_type' => 'passport',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $finalPhase = $this->activatePhase('final');
        $match = $this->createMatch($finalPhase);

        PhasePrize::query()->create([
            'phase_id' => $finalPhase->id,
            'ranking_from' => 1,
            'ranking_to' => 20,
            'football_role' => 'Ganador Bono',
            'prize_title' => 'Bono Super Carnes USD 200',
            'prize_type' => 'bono_200',
            'stock' => 20,
        ]);

        for ($index = 1; $index <= 21; $index++) {
            $player = $this->createClient(
                'Empatado Bloqueo '.$index,
                'bloqueo'.$index.'@example.com',
                '8-333-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            );
            $player->update([
                'registration_completed_at' => '2026-06-01 10:00:00',
                'registration_order_key' => 'same-order-key',
            ]);
            $this->createPrediction($match, $player, 5);
        }

        $response = $this->actingAs($admin)->post('/adminrepus1car/winners/generate', [
            'phase_id' => $finalPhase->id,
        ]);

        $response->assertSessionHasErrors('draw');
        $this->assertDatabaseCount('promo_winners', 0);
    }

    public function test_knockout_leaderboard_accumulates_knockout_phases_without_group_points(): void
    {
        $groupPhase = TournamentPhase::query()->where('slug', 'fase-grupos')->firstOrFail();
        $roundOf32 = $this->activatePhase('dieciseisavos');
        $roundOf16 = $this->activatePhase('octavos');

        $player = $this->createClient('Acumulador Finales', 'acumula@example.com', '8-111-0004');
        $rival = $this->createClient('Rival Finales', 'rival@example.com', '8-111-0005');

        $groupMatch = $this->createMatch($groupPhase);
        $roundOf32Match = $this->createMatch($roundOf32);
        $roundOf16Match = $this->createMatch($roundOf16);

        $this->createPrediction($groupMatch, $player, 50);
        $this->createPrediction($roundOf32Match, $player, 4);
        $this->createPrediction($roundOf16Match, $player, 5);
        $this->createPrediction($roundOf16Match, $rival, 8);

        $leaderboard = app(PromotionRankingService::class)->leaderboardForPhase($roundOf16->id, 10);
        $playerRow = $leaderboard->firstWhere('user_id', $player->id);
        $rivalRow = $leaderboard->firstWhere('user_id', $rival->id);

        $this->assertSame(9.0, $playerRow['prediction_points']);
        $this->assertSame(9.0, $playerRow['total_points']);
        $this->assertSame(8.0, $rivalRow['prediction_points']);
        $this->assertSame([$player->id, $rival->id], $leaderboard->pluck('user_id')->all());
    }

    public function test_recalculation_moves_prediction_points_to_current_match_phase(): void
    {
        $groupPhase = TournamentPhase::query()->where('slug', 'fase-grupos')->firstOrFail();
        $knockoutPhase = $this->activatePhase('dieciseisavos');

        $player = $this->createClient('Fase Corregida', 'fase-corregida@example.com', '8-111-0016');
        $match = $this->createMatch($knockoutPhase);
        $match->update([
            'status' => 'final',
            'home_score' => 2,
            'away_score' => 1,
        ]);

        $prediction = MatchPrediction::query()->create([
            'match_id' => $match->id,
            'user_id' => $player->id,
            'phase_id' => $groupPhase->id,
            'predicted_home_score' => 2,
            'predicted_away_score' => 1,
            'points_awarded' => 0,
            'result_type' => 'pending',
        ]);

        app(TournamentScoring::class)->recalculateForMatch($match->fresh('phase', 'predictions'));

        $this->assertSame($knockoutPhase->id, $prediction->fresh()->phase_id);

        $groupLeaderboard = app(PromotionRankingService::class)->leaderboardForPhase($groupPhase->id, 10);
        $knockoutLeaderboard = app(PromotionRankingService::class)->leaderboardForPhase($knockoutPhase->id, 10);

        $this->assertSame(0.0, $groupLeaderboard->firstWhere('user_id', $player->id)['prediction_points']);
        $this->assertSame(5.0, $knockoutLeaderboard->firstWhere('user_id', $player->id)['prediction_points']);
    }

    public function test_client_bootstrap_phase_goals_use_accumulated_knockout_points(): void
    {
        $groupPhase = TournamentPhase::query()->where('slug', 'fase-grupos')->firstOrFail();
        $roundOf32 = $this->activatePhase('dieciseisavos');
        $roundOf16 = $this->activatePhase('octavos');

        $player = $this->createClient('Resumen Finales', 'resumen@example.com', '8-111-0007');

        $this->createPrediction($this->createMatch($groupPhase), $player, 50);
        $this->createPrediction($this->createMatch($roundOf32), $player, 4);
        $this->createPrediction($this->createMatch($roundOf16), $player, 5);

        Sanctum::actingAs($player);

        $response = $this->getJson('/api/client/bootstrap');

        $response
            ->assertOk()
            ->assertJsonPath('active_phase.slug', 'dieciseisavos')
            ->assertJsonPath('phase_goals', 9);
    }

    public function test_client_bootstrap_returns_group_and_knockout_contests_separately(): void
    {
        $groupPhase = TournamentPhase::query()->where('slug', 'fase-grupos')->firstOrFail();
        $roundOf32 = $this->activatePhase('dieciseisavos');
        $finalPhase = $this->activatePhase('final');

        $player = $this->createClient('Dos Concursos', 'dos-concursos@example.com', '8-111-0013');
        $rival = $this->createClient('Rival Dos Concursos', 'rival-dos@example.com', '8-111-0014');

        $this->createPrediction($this->createMatch($groupPhase), $player, 50);
        $this->createPrediction($this->createMatch($roundOf32), $player, 4);
        $this->createPrediction($this->createMatch($finalPhase), $player, 5);
        $this->createPrediction($this->createMatch($finalPhase), $rival, 12);

        Sanctum::actingAs($player);

        $response = $this->getJson('/api/client/bootstrap');

        $response
            ->assertOk()
            ->assertJsonPath('group_stage_contest.key', 'group_stage')
            ->assertJsonPath('group_stage_contest.phase.slug', 'fase-grupos')
            ->assertJsonPath('group_stage_contest.user_points', 50)
            ->assertJsonPath('group_stage_contest.user_rank', 1)
            ->assertJsonPath('knockout_contest.key', 'knockout')
            ->assertJsonPath('knockout_contest.phase.slug', 'final')
            ->assertJsonPath('knockout_contest.user_points', 9)
            ->assertJsonPath('knockout_contest.user_rank', 2);
    }

    public function test_prediction_closes_fifteen_minutes_before_kickoff_in_panama_time(): void
    {
        $user = $this->createClient('Participante Puntual', 'puntual@example.com', '8-111-0003');
        $phase = $this->activatePhase('octavos');
        $match = $this->createMatch($phase, now('America/Panama')->addMinutes(14)->setTimezone('UTC'));

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/client/matches/{$match->id}/predict", [
            'predicted_home_score' => 2,
            'predicted_away_score' => 1,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('match');
    }

    public function test_client_sees_future_active_knockout_phase_once_matches_are_loaded(): void
    {
        $user = $this->createClient('Participante Futuro', 'futuro@example.com', '8-111-0006');
        TournamentPhase::query()->where('slug', 'fase-grupos')->update(['is_active' => false]);

        $phase = TournamentPhase::query()->where('slug', 'cuartos')->firstOrFail();
        $phase->update([
            'is_active' => true,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(5),
        ]);

        $this->createMatch($phase, now()->addDays(3)->addHours(2));

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/client/phases');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'cuartos');
    }

    public function test_database_seeds_separate_semifinal_and_final_phases(): void
    {
        $this->assertDatabaseHas('tournament_phases', [
            'slug' => 'semifinal',
            'name' => 'Semifinal',
        ]);
        $this->assertDatabaseHas('tournament_phases', [
            'slug' => 'final',
            'name' => 'Final',
        ]);
    }

    public function test_live_score_maps_final_fixture_to_final_phase(): void
    {
        TournamentPhase::query()->firstOrCreate(
            ['slug' => 'final'],
            [
                'name' => 'Final',
                'stage_order' => 6,
                'starts_at' => now()->addDays(10),
                'ends_at' => now()->addDays(11),
                'exact_score_points' => 7,
                'outcome_points' => 3,
                'reset_phase_table' => false,
                'is_active' => true,
            ],
        );

        $service = new LiveScoreSyncService(new LiveScoreApiClient(new HttpFactory()));
        $method = new \ReflectionMethod($service, 'resolvePhaseFromFixture');
        $method->setAccessible(true);

        $phase = $method->invoke($service, [
            'round' => 'Final',
            'stage' => 'Knockout',
        ]);

        $this->assertSame('final', $phase->slug);
    }

    public function test_live_score_maps_short_knockout_round_labels_to_their_phases(): void
    {
        $service = new LiveScoreSyncService(new LiveScoreApiClient(new HttpFactory()));
        $method = new \ReflectionMethod($service, 'resolvePhaseFromFixture');
        $method->setAccessible(true);

        $expected = [
            'R32' => 'dieciseisavos',
            'R16' => 'octavos',
            'QF' => 'cuartos',
            'SF' => 'semifinal',
            '3PPO' => 'final',
            'F' => 'final',
        ];

        foreach ($expected as $roundLabel => $expectedSlug) {
            $phase = $method->invoke($service, [
                'round' => $roundLabel,
                'stage' => null,
                'group_name' => null,
            ]);

            $this->assertSame($expectedSlug, $phase->slug, "Round {$roundLabel} debe mapear a {$expectedSlug}.");
        }
    }

    public function test_final_phase_has_twenty_two_hundred_dollar_bonus_prizes(): void
    {
        $finalPhase = TournamentPhase::query()->where('slug', 'final')->firstOrFail();

        $this->assertDatabaseHas('phase_prizes', [
            'phase_id' => $finalPhase->id,
            'ranking_from' => 1,
            'ranking_to' => 20,
            'prize_title' => 'Bono Super Carnes USD 200',
            'prize_type' => 'bono_200',
            'stock' => 20,
        ]);
    }

    public function test_admin_can_export_winners_with_tiebreaker_criteria(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Export',
            'email' => 'admin-export@example.com',
            'cedula' => 'ADMIN-EXPORT',
            'document_type' => 'passport',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $groupPhase = TournamentPhase::query()->where('slug', 'fase-grupos')->firstOrFail();
        $player = $this->createClient('Exportable Uno', 'exportable@example.com', '8-111-0015');
        $player->update([
            'group_stage_goal_prediction' => 190,
            'registration_completed_at' => '2026-06-10 11:33:55',
            'registration_order_key' => '2026-06-10 11:33:55',
        ]);

        PhasePrize::query()->create([
            'phase_id' => $groupPhase->id,
            'ranking_from' => 1,
            'ranking_to' => 1,
            'football_role' => 'Goleador Estrella',
            'prize_title' => 'TV 50 pulgadas',
            'prize_type' => 'tv_50',
            'stock' => 1,
        ]);

        $match = $this->createMatch($groupPhase);
        $match->update([
            'status' => 'final',
            'home_score' => 2,
            'away_score' => 1,
        ]);
        $this->createPrediction($match, $player, 7);
        $this->createApprovedInvoice($player, $groupPhase, 1);

        $response = $this->actingAs($admin)->get('/adminrepus1car/winners/export?phase_id='.$groupPhase->id);

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('puesto,tipo_premio,participante,cedula,correo,telefono,sucursal,puntos,de_1_marc_exactos,d_2_facturas,d_3_monto_compras,d_4_goles_predichos,d_4_goles_reales,de_4_diferencia_goles,d_5_fecha_registro', $content);
        $this->assertStringContainsString('TV 50 pulgadas', $content);
        $this->assertStringContainsString('Exportable Uno', $content);
        $this->assertStringContainsString('8-111-0015', $content);
        $this->assertStringContainsString('190', $content);
        $this->assertStringContainsString('3', $content);
        $this->assertStringContainsString('187', $content);
    }

    private function activatePhase(string $slug): TournamentPhase
    {
        TournamentPhase::query()->where('slug', 'fase-grupos')->update(['is_active' => false]);

        $phase = TournamentPhase::query()->where('slug', $slug)->firstOrFail();
        $phase->update([
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(7),
        ]);

        return $phase->fresh();
    }

    private function createClient(string $name, string $email, string $cedula): User
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'cedula' => $cedula,
            'document_type' => 'cedula',
            'password' => bcrypt('secret'),
            'role' => 'client',
            'is_active' => true,
            'birthdate' => now()->subYears(25)->toDateString(),
            'resides_in_panama' => true,
            'is_employee' => false,
            'phone' => '+50761234567',
            'avatar_path' => 'avatars/test.jpg',
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

    private function createMatch(TournamentPhase $phase, mixed $kickoffAt = null): TournamentMatch
    {
        return TournamentMatch::query()->create([
            'phase_id' => $phase->id,
            'match_number' => 90 + $phase->id,
            'round_label' => $phase->name,
            'stage_label' => 'Eliminatoria',
            'home_team_id' => $this->insertTeam('Brasil '.$phase->id, 'BRA'.$phase->id),
            'away_team_id' => $this->insertTeam('Argentina '.$phase->id, 'ARG'.$phase->id),
            'favorite_side' => 'home',
            'kickoff_at' => $kickoffAt ?? now()->addHours(3),
            'home_score' => 2,
            'away_score' => 1,
            'status' => 'scheduled',
        ]);
    }

    private function createPrediction(TournamentMatch $match, User $user, int $points): void
    {
        MatchPrediction::query()->create([
            'match_id' => $match->id,
            'user_id' => $user->id,
            'phase_id' => $match->phase_id,
            'predicted_home_score' => 2,
            'predicted_away_score' => 1,
            'points_awarded' => $points,
            'result_type' => 'exact',
        ]);
    }

    private function createApprovedInvoice(User $user, TournamentPhase $phase, int $points): void
    {
        RegisteredInvoice::query()->create([
            'user_id' => $user->id,
            'cufe' => 'TEST-CUFE-'.$user->id.'-'.$phase->id,
            'qr_raw_text' => 'QR TEST',
            'invoice_number' => 'FAC-'.$user->id.'-'.$phase->id,
            'issued_at' => $phase->starts_at->copy()->addHour(),
            'purchase_amount' => 100,
            'points_awarded' => $points,
            'shots_awarded' => 0,
            'daily_points_capped' => false,
            'daily_invoice_limit_hit' => false,
            'status' => 'approved',
            'validation_status' => 'approved',
        ]);
    }

    private function insertTeam(string $name, string $code): int
    {
        return (int) \DB::table('teams')->insertGetId([
            'name' => $name,
            'code' => $code,
            'group_label' => null,
            'flag_emoji' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
