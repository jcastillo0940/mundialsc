<?php

namespace Tests\Feature;

use App\Models\TournamentMatch;
use App\Models\TournamentPhase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchesBackofficeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_reassign_match_teams_from_backoffice(): void
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

        $phase = TournamentPhase::query()->where('slug', 'octavos')->firstOrFail();

        $originalHomeTeamId = $this->insertTeam('Alemania', 'GER', 16);
        $originalAwayTeamId = $this->insertTeam('Inglaterra', 'ENG', 4);
        $newHomeTeamId = $this->insertTeam('Brasil', 'BRA', 5);
        $newAwayTeamId = $this->insertTeam('Argentina', 'ARG', 1);

        $match = TournamentMatch::query()->create([
            'phase_id' => $phase->id,
            'match_number' => 58,
            'group_label' => null,
            'round_label' => 'Octavos',
            'stage_label' => 'Eliminatoria',
            'home_team_id' => $originalHomeTeamId,
            'away_team_id' => $originalAwayTeamId,
            'favorite_side' => 'away',
            'kickoff_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($admin)->put("/adminrepus1car/matches/{$match->id}", [
            'home_team_id' => $newHomeTeamId,
            'away_team_id' => $newAwayTeamId,
            'home_score' => null,
            'away_score' => null,
            'status' => 'scheduled',
            'favorite_side' => 'none',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Partido actualizado.');

        $match->refresh();

        $this->assertSame($newHomeTeamId, (int) $match->home_team_id);
        $this->assertSame($newAwayTeamId, (int) $match->away_team_id);
        $this->assertSame('away', $match->favorite_side);
    }

    private function insertTeam(string $name, string $code, ?int $rankingFifa = null): int
    {
        return (int) \DB::table('teams')->insertGetId([
            'name' => $name,
            'code' => $code,
            'group_label' => null,
            'flag_emoji' => null,
            'ranking_fifa' => $rankingFifa,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
