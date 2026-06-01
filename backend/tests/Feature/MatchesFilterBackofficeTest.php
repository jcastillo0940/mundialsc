<?php

namespace Tests\Feature;

use App\Models\TournamentMatch;
use App\Models\TournamentPhase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchesFilterBackofficeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_matches_by_phase_and_team(): void
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
        $quarterPhase = TournamentPhase::query()->where('slug', 'cuartos')->firstOrFail();

        $panamaId = $this->insertTeam('Panama', 'PAN');
        $mexicoId = $this->insertTeam('Mexico', 'MEX');
        $brazilId = $this->insertTeam('Brasil', 'BRA');
        $argentinaId = $this->insertTeam('Argentina', 'ARG');

        TournamentMatch::query()->create([
            'phase_id' => $groupPhase->id,
            'match_number' => 1,
            'group_label' => 'A',
            'home_team_id' => $panamaId,
            'away_team_id' => $mexicoId,
            'favorite_side' => 'away',
            'kickoff_at' => now()->addHour(),
            'status' => 'scheduled',
        ]);

        TournamentMatch::query()->create([
            'phase_id' => $quarterPhase->id,
            'match_number' => 57,
            'group_label' => null,
            'round_label' => 'Cuartos',
            'stage_label' => 'Eliminatoria',
            'home_team_id' => $brazilId,
            'away_team_id' => $argentinaId,
            'favorite_side' => 'home',
            'kickoff_at' => now()->addHours(2),
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($admin)->get("/adminrepus1car/matches?phase_id={$quarterPhase->id}&team_id={$argentinaId}");

        $response->assertOk();
        $response->assertSee('Cuartos de Final');
        $response->assertSee('Brasil');
        $response->assertSee('Argentina');
        $response->assertSee('Partido #57');

        $matches = $response->viewData('matches');

        $this->assertCount(1, $matches);
        $this->assertSame(57, (int) $matches->first()->match_number);
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
