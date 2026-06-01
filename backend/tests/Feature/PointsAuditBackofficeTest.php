<?php

namespace Tests\Feature;

use App\Models\MatchPrediction;
use App\Models\TournamentMatch;
use App\Models\TournamentPhase;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointsAuditBackofficeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_combined_points_audit_for_wallet_and_prediction_sources(): void
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

        $player = User::query()->create([
            'name' => 'Gaby Torres',
            'email' => 'gaby@example.com',
            'cedula' => '8-864-1164',
            'document_type' => 'cedula',
            'password' => bcrypt('secret'),
            'role' => 'client',
            'is_active' => true,
        ]);

        $phase = $this->groupStagePhase();

        $homeTeamId = $this->insertTeam('Panama', 'PAN');
        $awayTeamId = $this->insertTeam('Mexico', 'MEX');

        $match = TournamentMatch::query()->create([
            'phase_id' => $phase->id,
            'match_number' => 1,
            'group_label' => 'A',
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
            'favorite_side' => 'away',
            'kickoff_at' => now()->subDay(),
            'home_score' => 2,
            'away_score' => 1,
            'status' => 'final',
        ]);

        $wallet = Wallet::query()->create([
            'user_id' => $player->id,
            'goals_balance' => 1,
            'shots_balance' => 0,
            'lifetime_goals_earned' => 1,
            'lifetime_shots_earned' => 0,
        ]);

        WalletMovement::query()->create([
            'wallet_id' => $wallet->id,
            'user_id' => $player->id,
            'type' => 'invoice_goal_awarded',
            'resource_type' => 'registered_invoice',
            'resource_id' => 99,
            'goals_delta' => 1,
            'shots_delta' => 0,
            'notes' => 'Factura validada y punto acreditado.',
            'meta' => [
                'source' => 'invoice',
                'rule_code' => 'invoice_goal_awarded',
                'rule_label' => 'Factura aprobada mayor al minimo',
                'rule_snapshot' => [
                    'minimum_amount' => 25,
                    'goal_value' => 1,
                ],
            ],
            'created_at' => now()->subHours(3),
        ]);

        MatchPrediction::query()->create([
            'match_id' => $match->id,
            'user_id' => $player->id,
            'phase_id' => $phase->id,
            'predicted_home_score' => 2,
            'predicted_away_score' => 1,
            'points_awarded' => 6,
            'result_type' => 'exact',
        ]);

        $response = $this->actingAs($admin)->get('/adminrepus1car/points-audit');

        $response->assertOk();
        $response->assertSee('Auditoria de puntos');
        $response->assertSee('Gaby Torres');
        $response->assertSee('invoice_goal_awarded');
        $response->assertSee('Pronostico exacto');
        $response->assertSee('Factura aprobada mayor al minimo');
    }

    public function test_admin_can_filter_points_audit_by_source(): void
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

        $player = User::query()->create([
            'name' => 'Diana Paola',
            'email' => 'diana@example.com',
            'cedula' => '8-864-1165',
            'document_type' => 'cedula',
            'password' => bcrypt('secret'),
            'role' => 'client',
            'is_active' => true,
        ]);

        $phase = $this->groupStagePhase();

        $homeTeamId = $this->insertTeam('Costa Rica', 'CRC');
        $awayTeamId = $this->insertTeam('USA', 'USA');

        $match = TournamentMatch::query()->create([
            'phase_id' => $phase->id,
            'match_number' => 2,
            'group_label' => 'B',
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
            'favorite_side' => 'away',
            'kickoff_at' => now()->subDay(),
            'home_score' => 1,
            'away_score' => 1,
            'status' => 'final',
        ]);

        MatchPrediction::query()->create([
            'match_id' => $match->id,
            'user_id' => $player->id,
            'phase_id' => $phase->id,
            'predicted_home_score' => 1,
            'predicted_away_score' => 1,
            'points_awarded' => 5,
            'result_type' => 'exact',
        ]);

        $response = $this->actingAs($admin)->get('/adminrepus1car/points-audit?source=prediction');

        $response->assertOk();
        $response->assertSee('Pronostico exacto');
        $response->assertDontSee('invoice_goal_awarded');
    }

    private function insertTeam(string $name, string $code): int
    {
        return (int) \DB::table('teams')->insertGetId([
            'name' => $name,
            'code' => $code,
            'group_label' => 'A',
            'flag_emoji' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function groupStagePhase(): TournamentPhase
    {
        return TournamentPhase::query()->firstOrCreate(
            ['slug' => 'fase-grupos'],
            [
                'name' => 'Fase de Grupos',
                'stage_order' => 1,
                'starts_at' => now()->subDays(5),
                'ends_at' => now()->addDays(5),
                'exact_score_points' => 3,
                'outcome_points' => 1,
                'reset_phase_table' => false,
                'is_active' => true,
            ],
        );
    }
}
