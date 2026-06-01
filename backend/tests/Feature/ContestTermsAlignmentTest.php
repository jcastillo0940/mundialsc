<?php

namespace Tests\Feature;

use App\Models\TournamentMatch;
use App\Models\TournamentPhase;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContestTermsAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_bootstrap_lists_active_elimination_phase(): void
    {
        $user = $this->createEligibleClient();

        TournamentPhase::query()->where('slug', 'fase-grupos')->update(['is_active' => false]);

        $phase = TournamentPhase::query()->where('slug', 'octavos')->firstOrFail();
        $phase->update([
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        TournamentMatch::query()->create([
            'phase_id' => $phase->id,
            'match_number' => 55,
            'group_label' => null,
            'round_label' => 'Octavos',
            'stage_label' => 'Eliminatoria',
            'home_team_id' => $this->insertTeam('Alemania', 'GER'),
            'away_team_id' => $this->insertTeam('Inglaterra', 'ENG'),
            'favorite_side' => 'home',
            'kickoff_at' => now()->addHours(4),
            'status' => 'scheduled',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/client/bootstrap');

        $response
            ->assertOk()
            ->assertJsonPath('active_phase.slug', 'octavos');
    }

    public function test_client_can_submit_prediction_for_active_elimination_match_before_kickoff(): void
    {
        $user = $this->createEligibleClient();

        TournamentPhase::query()->where('slug', 'fase-grupos')->update(['is_active' => false]);

        $phase = TournamentPhase::query()->where('slug', 'octavos')->firstOrFail();
        $phase->update([
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(2),
        ]);

        $match = TournamentMatch::query()->create([
            'phase_id' => $phase->id,
            'match_number' => 99,
            'group_label' => null,
            'round_label' => 'Octavos',
            'stage_label' => 'Eliminatoria',
            'home_team_id' => $this->insertTeam('Brasil', 'BRA'),
            'away_team_id' => $this->insertTeam('Argentina', 'ARG'),
            'favorite_side' => 'home',
            'kickoff_at' => now()->addHours(6),
            'status' => 'scheduled',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/client/matches/{$match->id}/predict", [
            'predicted_home_score' => 2,
            'predicted_away_score' => 1,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('prediction.phase_id', $phase->id);
    }

    public function test_group_stage_is_hidden_after_it_ends_if_next_knockout_has_no_matches_loaded(): void
    {
        $user = $this->createEligibleClient();

        $groupStage = TournamentPhase::query()->where('slug', 'fase-grupos')->firstOrFail();
        $groupStage->update([
            'is_active' => true,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subMinute(),
        ]);

        $nextPhase = TournamentPhase::query()->where('slug', 'dieciseisavos')->firstOrFail();
        $nextPhase->update([
            'is_active' => true,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(4),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/client/bootstrap');

        $response
            ->assertOk()
            ->assertJsonPath('active_phase', null);

        $phasesResponse = $this->getJson('/api/client/phases');

        $phasesResponse
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_knockout_phase_stays_hidden_when_match_has_no_resolved_teams(): void
    {
        $user = $this->createEligibleClient();

        TournamentPhase::query()->where('slug', 'fase-grupos')->update(['is_active' => false]);

        $phase = TournamentPhase::query()->where('slug', 'cuartos')->firstOrFail();
        $phase->update([
            'is_active' => true,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
        ]);

        TournamentMatch::query()->create([
            'phase_id' => $phase->id,
            'match_number' => 61,
            'group_label' => null,
            'round_label' => 'Cuartos',
            'stage_label' => 'Eliminatoria',
            'home_team_id' => 999001,
            'away_team_id' => 999002,
            'favorite_side' => 'none',
            'kickoff_at' => now()->addHours(3),
            'status' => 'scheduled',
        ]);

        Sanctum::actingAs($user);

        $bootstrapResponse = $this->getJson('/api/client/bootstrap');

        $bootstrapResponse
            ->assertOk()
            ->assertJsonPath('active_phase', null);

        $phasesResponse = $this->getJson('/api/client/phases');

        $phasesResponse
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $matchesResponse = $this->getJson('/api/client/matches');

        $matchesResponse
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_knockout_phase_stays_hidden_when_match_only_has_placeholder_winner_slots(): void
    {
        $user = $this->createEligibleClient();

        TournamentPhase::query()->where('slug', 'fase-grupos')->update(['is_active' => false]);

        $phase = TournamentPhase::query()->where('slug', 'octavos')->firstOrFail();
        $phase->update([
            'is_active' => true,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
        ]);

        TournamentMatch::query()->create([
            'phase_id' => $phase->id,
            'match_number' => 49,
            'group_label' => null,
            'round_label' => 'Octavos',
            'stage_label' => 'Eliminatoria',
            'home_team_id' => $this->insertTeam('Winner Group C', 'WINNERGROUPC'),
            'away_team_id' => $this->insertTeam('Runner-up Group F', 'RUNNERUPGROUPF'),
            'favorite_side' => 'none',
            'kickoff_at' => now()->addHours(5),
            'status' => 'scheduled',
        ]);

        Sanctum::actingAs($user);

        $bootstrapResponse = $this->getJson('/api/client/bootstrap');

        $bootstrapResponse
            ->assertOk()
            ->assertJsonPath('active_phase', null);

        $phasesResponse = $this->getJson('/api/client/phases');

        $phasesResponse
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $matchesResponse = $this->getJson('/api/client/matches');

        $matchesResponse
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    private function createEligibleClient(): User
    {
        $user = User::query()->create([
            'name' => 'Participante Oficial',
            'email' => 'cliente@example.com',
            'cedula' => '8-864-9999',
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
