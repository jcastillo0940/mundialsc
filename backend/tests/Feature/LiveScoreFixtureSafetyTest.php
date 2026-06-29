<?php

namespace Tests\Feature;

use App\Models\LiveScoreSetting;
use App\Models\TournamentMatch;
use App\Models\TournamentPhase;
use App\Support\LiveScoreApiClient;
use App\Support\LiveScoreSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Tests\TestCase;

class LiveScoreFixtureSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixture_sync_can_update_scheduled_matches(): void
    {
        LiveScoreSetting::query()->firstOrCreate([
            'provider_name' => 'live_score_api',
        ], [
            'is_enabled' => true,
            'competition_id' => 'wc2026',
            'season' => '2026',
            'lang' => 'es',
            'auto_sync_commentary' => false,
        ]);

        $phase = TournamentPhase::query()->where('slug', 'fase-grupos')->firstOrFail();
        $originalHomeTeamId = $this->insertTeam('Panama', 'PAN');
        $originalAwayTeamId = $this->insertTeam('Mexico', 'MEX');

        TournamentMatch::query()->create([
            'external_fixture_id' => 9100,
            'phase_id' => $phase->id,
            'match_number' => 11,
            'group_label' => 'A',
            'round_label' => 'Group Stage',
            'stage_label' => 'Groups',
            'home_team_id' => $originalHomeTeamId,
            'away_team_id' => $originalAwayTeamId,
            'favorite_side' => 'away',
            'kickoff_at' => now()->addDay(),
            'status' => 'scheduled',
            'provider' => 'live_score_api',
            'provider_status' => 'LOCAL_scheduled',
        ]);

        $service = new LiveScoreSyncService(new FixtureSafetyFakeLiveScoreClient([
            $this->fixturePayload(9100),
        ]));

        $run = $service->syncFixtures();

        $this->assertSame('completed', $run->status);
        $this->assertSame(1, (int) $run->records_updated);
        $this->assertSame(0, (int) $run->records_skipped);

        $match = TournamentMatch::query()->where('external_fixture_id', 9100)->firstOrFail();

        $this->assertSame('scheduled', $match->status);
        $this->assertSame('NS', $match->provider_status);
        $this->assertNotSame($originalHomeTeamId, (int) $match->home_team_id);
        $this->assertNotSame($originalAwayTeamId, (int) $match->away_team_id);
        $this->assertSame('Nuevo Estadio', $match->venue_name);
    }

    public function test_fixture_sync_does_not_overwrite_closed_or_live_matches(): void
    {
        LiveScoreSetting::query()->firstOrCreate([
            'provider_name' => 'live_score_api',
        ], [
            'is_enabled' => true,
            'competition_id' => 'wc2026',
            'season' => '2026',
            'lang' => 'es',
            'auto_sync_commentary' => false,
        ]);

        $phase = TournamentPhase::query()->where('slug', 'fase-grupos')->firstOrFail();
        $originalHomeTeamId = $this->insertTeam('Panama', 'PAN');
        $originalAwayTeamId = $this->insertTeam('Mexico', 'MEX');

        foreach (['locked', 'final', 'void'] as $index => $status) {
            TournamentMatch::query()->create([
                'external_fixture_id' => 9000 + $index,
                'phase_id' => $phase->id,
                'match_number' => 10 + $index,
                'group_label' => 'A',
                'round_label' => 'Group Stage',
                'stage_label' => 'Groups',
                'home_team_id' => $originalHomeTeamId,
                'away_team_id' => $originalAwayTeamId,
                'favorite_side' => 'away',
                'kickoff_at' => now()->subDay(),
                'home_score' => 2,
                'away_score' => 1,
                'status' => $status,
                'provider' => 'live_score_api',
                'provider_status' => 'LOCAL_'.$status,
            ]);
        }

        $service = new LiveScoreSyncService(new FixtureSafetyFakeLiveScoreClient([
            $this->fixturePayload(9000),
            $this->fixturePayload(9001),
            $this->fixturePayload(9002),
        ]));

        $run = $service->syncFixtures();

        $this->assertSame('completed', $run->status);
        $this->assertSame(0, (int) $run->records_updated);
        $this->assertSame(3, (int) $run->records_skipped);

        foreach (['locked', 'final', 'void'] as $index => $status) {
            $match = TournamentMatch::query()->where('external_fixture_id', 9000 + $index)->firstOrFail();

            $this->assertSame($status, $match->status);
            $this->assertSame($originalHomeTeamId, (int) $match->home_team_id);
            $this->assertSame($originalAwayTeamId, (int) $match->away_team_id);
            $this->assertSame(2, (int) $match->home_score);
            $this->assertSame(1, (int) $match->away_score);
            $this->assertSame('LOCAL_'.$status, $match->provider_status);
        }
    }

    private function fixturePayload(int $fixtureId): array
    {
        return [
            'id' => $fixtureId,
            'fixture_id' => $fixtureId,
            'group_name' => 'Group A',
            'round' => 'Group Stage',
            'stage' => 'Groups',
            'date' => now()->addDay()->toDateString(),
            'time' => '20:00:00',
            'status' => 'NS',
            'location' => 'Nuevo Estadio',
            'home' => [
                'id' => 7000 + $fixtureId,
                'name' => 'Brasil',
                'code' => 'BRA',
            ],
            'away' => [
                'id' => 8000 + $fixtureId,
                'name' => 'Argentina',
                'code' => 'ARG',
            ],
            'competition' => [
                'name' => 'World Cup',
            ],
        ];
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
}

class FixtureSafetyFakeLiveScoreClient extends LiveScoreApiClient
{
    public function __construct(private readonly array $fixtureItems)
    {
        parent::__construct(new HttpFactory());
    }

    public function fixtures(array $params = []): array
    {
        return $this->fixtureItems;
    }

    public function competitionGroups(array $params = []): array
    {
        return [];
    }

    public function participants(array $params = []): array
    {
        return [];
    }
}
