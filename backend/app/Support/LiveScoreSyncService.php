<?php

namespace App\Support;

use App\Models\LiveScoreCommentaryEvent;
use App\Models\LiveScoreSetting;
use App\Models\LiveScoreSyncRun;
use App\Models\Team;
use App\Models\TournamentMatch;
use App\Models\TournamentPhase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LiveScoreSyncService
{
    public function __construct(
        private readonly LiveScoreApiClient $client,
    ) {
    }

    public function syncFixtures(array $filters = [], ?int $requestedByUserId = null): LiveScoreSyncRun
    {
        $run = $this->startRun('fixtures', $filters, $requestedByUserId);

        try {
            $fixtureParams = $this->buildFixtureParams($filters);
            $competitionCatalog = $this->buildCompetitionCatalog($fixtureParams);
            $items = $this->client->fixtures($fixtureParams);
            $created = 0;
            $updated = 0;
            $skipped = 0;

            foreach ($items as $item) {
                $fixtureId = (int) ($item['id'] ?? $item['fixture_id'] ?? 0);
                if (! $fixtureId) {
                    $skipped++;
                    continue;
                }

                $home = $item['home'] ?? [];
                $away = $item['away'] ?? [];
                if (! is_array($home) || ! is_array($away) || $home === [] || $away === []) {
                    $skipped++;
                    continue;
                }

                $externalGroupId = isset($item['group_id']) ? (int) $item['group_id'] : null;
                $group = $externalGroupId ? ($competitionCatalog['groups'][$externalGroupId] ?? null) : null;
                $homeTeam = $this->upsertTeam($home, $competitionCatalog['participants'][(int) ($home['id'] ?? 0)] ?? null, $group);
                $awayTeam = $this->upsertTeam($away, $competitionCatalog['participants'][(int) ($away['id'] ?? 0)] ?? null, $group);
                $match = TournamentMatch::query()->where('external_fixture_id', $fixtureId)->first();

                $payload = [
                    'phase_id' => $this->resolvePhaseFromFixture($item)->id,
                    'match_number' => $item['fixture_id'] ?? $item['id'] ?? null,
                    'external_group_id' => $externalGroupId,
                    'group_label' => $this->resolveGroupLabel($item, $group),
                    'round_label' => $this->stringOrNull($item['round'] ?? null),
                    'stage_label' => $this->resolveStageLabel($item, $group),
                    'venue_name' => $this->stringOrNull($item['location'] ?? $home['stadium'] ?? null),
                    'home_team_id' => $homeTeam->id,
                    'away_team_id' => $awayTeam->id,
                    'favorite_side' => $this->resolveFavoriteSide($homeTeam, $awayTeam, $item),
                    'kickoff_at' => $this->resolveKickoff($item),
                    'status' => 'scheduled',
                    'provider' => 'live_score_api',
                    'provider_status' => $item['status'] ?? 'fixture',
                    'provider_competition_name' => $this->stringOrNull($item['competition']['name'] ?? null),
                    'kickoff_timezone' => 'UTC',
                    'raw_provider_payload' => $item,
                ];

                if ($match) {
                    $match->update($payload);
                    $updated++;
                } else {
                    TournamentMatch::query()->create(array_merge($payload, [
                        'external_fixture_id' => $fixtureId,
                    ]));
                    $created++;
                }
            }

            return $this->finishRun($run, 'completed', $created, $updated, $skipped);
        } catch (\Throwable $exception) {
            return $this->finishRun($run, 'failed', 0, 0, 0, $exception->getMessage());
        }
    }

    public function syncLive(array $filters = [], ?int $requestedByUserId = null): LiveScoreSyncRun
    {
        $run = $this->startRun('live', $filters, $requestedByUserId);

        try {
            $items = $this->client->live($this->buildLiveParams($filters));
            $updated = 0;
            $skipped = 0;

            foreach ($items as $item) {
                $fixtureId = (int) ($item['fixture_id'] ?? 0);
                $matchId = (int) ($item['id'] ?? 0);

                if (! $fixtureId || ! $matchId) {
                    $skipped++;
                    continue;
                }

                $match = TournamentMatch::query()->where('external_fixture_id', $fixtureId)->first();
                if (! $match) {
                    $skipped++;
                    continue;
                }

                [$homeScore, $awayScore] = $this->parseScore($item['scores']['score'] ?? null);
                $status = $this->mapProviderStatus($item['status'] ?? null);

                $match->update([
                    'external_match_id' => $matchId,
                    'home_score' => $homeScore,
                    'away_score' => $awayScore,
                    'status' => $status,
                    'provider' => 'live_score_api',
                    'provider_status' => $item['status'] ?? null,
                    'venue_name' => $this->stringOrNull($item['location'] ?? $match->venue_name),
                    'provider_competition_name' => $this->stringOrNull($item['competition']['name'] ?? $match->provider_competition_name),
                    'live_score_last_synced_at' => now(),
                    'raw_provider_payload' => $item,
                ]);

                if ($status === 'final' && $homeScore !== null && $awayScore !== null) {
                    app(TournamentScoring::class)->recalculateForMatch($match->fresh('phase', 'predictions'));
                }

                $updated++;
            }

            return $this->finishRun($run, 'completed', 0, $updated, $skipped);
        } catch (\Throwable $exception) {
            return $this->finishRun($run, 'failed', 0, 0, 0, $exception->getMessage());
        }
    }

    public function syncCommentary(?TournamentMatch $match = null, array $filters = [], ?int $requestedByUserId = null): LiveScoreSyncRun
    {
        $run = $this->startRun('commentary', $filters, $requestedByUserId);

        try {
            $matches = $match
                ? collect([$match])
                : $this->commentaryCandidates($filters);

            $created = 0;
            $updated = 0;
            $skipped = 0;

            foreach ($matches as $tournamentMatch) {
                if (! $tournamentMatch->external_match_id) {
                    $skipped++;
                    continue;
                }

                $events = $this->client->commentary((int) $tournamentMatch->external_match_id, [
                    'lang' => $filters['lang'] ?? $this->settings()->lang ?? config('services.live_score.default_lang'),
                    'from_second' => $filters['from_second'] ?? null,
                    'to_second' => $filters['to_second'] ?? null,
                ]);

                foreach ($events as $event) {
                    $externalEventId = (int) ($event['id'] ?? 0);
                    if (! $externalEventId) {
                        $skipped++;
                        continue;
                    }

                    $record = LiveScoreCommentaryEvent::query()->where('external_event_id', $externalEventId)->first();
                    $payload = [
                        'tournament_match_id' => $tournamentMatch->id,
                        'external_match_id' => (int) $tournamentMatch->external_match_id,
                        'event_type' => (string) ($event['event_type'] ?? 'UNKNOWN'),
                        'minute' => $event['minute'] ?? null,
                        'second_label' => isset($event['second']) ? (string) $event['second'] : null,
                        'match_second' => isset($event['match_second']) ? (int) $event['match_second'] : null,
                        'comment_text' => $event['comment'] ?? null,
                        'text_label' => $event['text'] ?? null,
                        'pos_x' => $event['pos_x'] ?? null,
                        'pos_y' => $event['pos_y'] ?? null,
                        'side' => $event['side'] ?? null,
                        'external_team_id' => $event['team']['id'] ?? null,
                        'team_name' => $this->translateCountryName($event['team']['name'] ?? null),
                        'external_player_id' => $event['player']['id'] ?? null,
                        'player_name' => $event['player']['name'] ?? null,
                        'external_player_2_id' => $event['player_2']['id'] ?? null,
                        'player_2_name' => $event['player_2']['name'] ?? null,
                        'provider_created_at' => $this->nullableDate($event['created_at'] ?? null),
                        'provider_updated_at' => $this->nullableDate($event['updated_at'] ?? null),
                        'raw_payload' => $event,
                    ];

                    if ($record) {
                        $record->update($payload);
                        $updated++;
                    } else {
                        LiveScoreCommentaryEvent::query()->create(array_merge($payload, [
                            'external_event_id' => $externalEventId,
                        ]));
                        $created++;
                    }
                }

                $tournamentMatch->update([
                    'commentary_last_synced_at' => now(),
                ]);
            }

            return $this->finishRun($run, 'completed', $created, $updated, $skipped);
        } catch (\Throwable $exception) {
            return $this->finishRun($run, 'failed', 0, 0, 0, $exception->getMessage());
        }
    }

    private function settings(): LiveScoreSetting
    {
        return LiveScoreSetting::query()->firstOrFail();
    }

    public function liveSyncIntervalMinutes(): int
    {
        return max(1, (int) ($this->settings()->live_sync_interval_minutes ?: config('services.live_score.live_sync_interval_minutes', 3)));
    }

    public function commentarySyncIntervalMinutes(): int
    {
        return max(1, (int) ($this->settings()->commentary_sync_interval_minutes ?: config('services.live_score.commentary_sync_interval_minutes', 3)));
    }

    public function fixturesSyncIntervalHours(): int
    {
        return max(1, (int) ($this->settings()->fixtures_sync_interval_hours ?: config('services.live_score.fixtures_sync_interval_hours', 24)));
    }

    public function shouldRunNow(string $syncType, int $intervalSeconds): bool
    {
        $lastRun = LiveScoreSyncRun::query()
            ->where('sync_type', $syncType)
            ->where('status', 'completed')
            ->orderByDesc('finished_at')
            ->first();

        if (! $lastRun?->finished_at) {
            return true;
        }

        return $lastRun->finished_at->diffInSeconds(now()) >= $intervalSeconds;
    }

    private function startRun(string $type, array $context, ?int $requestedByUserId): LiveScoreSyncRun
    {
        return LiveScoreSyncRun::query()->create([
            'sync_type' => $type,
            'status' => 'started',
            'requested_by_user_id' => $requestedByUserId,
            'context' => $context,
            'started_at' => now(),
        ]);
    }

    private function finishRun(LiveScoreSyncRun $run, string $status, int $created, int $updated, int $skipped, ?string $error = null): LiveScoreSyncRun
    {
        $run->update([
            'status' => $status,
            'records_created' => $created,
            'records_updated' => $updated,
            'records_skipped' => $skipped,
            'error_message' => $error,
            'finished_at' => now(),
        ]);

        return $run->fresh();
    }

    private function commentaryCandidates(array $filters): Collection
    {
        $now = now();
        $windowHours = (int) ($filters['window_hours'] ?? 6);
        $windowHours = max(1, min($windowHours, 24));
        $from = $filters['from'] ?? $now->copy()->subHours($windowHours)->toDateTimeString();
        $to = $filters['to'] ?? $now->copy()->addHours($windowHours)->toDateTimeString();

        return TournamentMatch::query()
            ->whereNotNull('external_match_id')
            ->where('provider', 'live_score_api')
            ->where('status', 'locked')
            ->whereBetween('kickoff_at', [$from, $to])
            ->get();
    }

    private function buildFixtureParams(array $filters): array
    {
        $settings = $this->settings();

        return array_filter([
            'competition_id' => $filters['competition_id'] ?? $settings->competition_id ?? config('services.live_score.competition_id'),
            'competition_ids' => $filters['competition_ids'] ?? $settings->competition_ids ?? config('services.live_score.competition_ids'),
            'season' => $filters['season'] ?? $settings->season ?? config('services.live_score.season'),
            'from' => $filters['from'] ?? optional($settings->sync_from_date)->toDateString(),
            'to' => $filters['to'] ?? optional($settings->sync_to_date)->toDateString(),
            'lang' => $filters['lang'] ?? $settings->lang ?? config('services.live_score.default_lang'),
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function buildLiveParams(array $filters): array
    {
        $settings = $this->settings();

        return array_filter([
            'competition_id' => $filters['competition_id'] ?? $settings->competition_id ?? config('services.live_score.competition_id'),
            'lang' => $filters['lang'] ?? $settings->lang ?? config('services.live_score.default_lang'),
            'fixture_id' => $filters['fixture_id'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function buildCompetitionCatalog(array $fixtureParams): array
    {
        $competitionId = $fixtureParams['competition_id'] ?? null;
        if (! $competitionId) {
            return ['groups' => [], 'participants' => []];
        }

        $groups = collect($this->client->competitionGroups([
            'competition_id' => $competitionId,
        ]))
            ->filter(fn ($group) => is_array($group) && isset($group['id']))
            ->mapWithKeys(fn (array $group) => [(int) $group['id'] => $group])
            ->all();

        $participants = $this->normalizeParticipants($this->client->participants([
            'competition_id' => $competitionId,
            'season' => $fixtureParams['season'] ?? null,
        ]));

        return [
            'groups' => $groups,
            'participants' => $participants,
        ];
    }

    private function normalizeParticipants(array $items): array
    {
        $participants = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (isset($item['id'], $item['name'])) {
                $participants[(int) $item['id']] = $item;
                continue;
            }

            foreach ($item as $nested) {
                if (is_array($nested) && array_is_list($nested)) {
                    foreach ($this->normalizeParticipants($nested) as $teamId => $participant) {
                        $participants[$teamId] = $participant;
                    }
                }
            }
        }

        return $participants;
    }

    private function resolvePhaseFromFixture(array $item): TournamentPhase
    {
        $groupName = Str::lower((string) ($item['group_name'] ?? $item['group'] ?? ''));
        $stageName = Str::lower((string) ($item['round'] ?? $item['stage'] ?? $item['competition']['name'] ?? ''));

        if (str_contains($groupName, 'group') || preg_match('/^[a-z]$/', $groupName)) {
            return TournamentPhase::query()->where('slug', 'fase-grupos')->firstOrFail();
        }
        if (str_contains($stageName, 'round of 32') || str_contains($stageName, 'last 32') || str_contains($stageName, 'sixteenth')) {
            return TournamentPhase::query()->where('slug', 'dieciseisavos')->firstOrFail();
        }
        if (str_contains($stageName, 'round of 16') || str_contains($stageName, 'octavos')) {
            return TournamentPhase::query()->where('slug', 'octavos')->firstOrFail();
        }
        if (str_contains($stageName, 'quarter')) {
            return TournamentPhase::query()->where('slug', 'cuartos')->firstOrFail();
        }
        if (str_contains($stageName, 'semi') || str_contains($stageName, 'third') || str_contains($stageName, 'final')) {
            return TournamentPhase::query()->where('slug', 'semifinal-final')->firstOrFail();
        }

        return TournamentPhase::query()->orderBy('stage_order')->firstOrFail();
    }

    private function upsertTeam(array $providerTeam, ?array $participant = null, ?array $group = null): Team
    {
        $externalId = isset($providerTeam['id']) ? (int) $providerTeam['id'] : null;
        $providerName = (string) ($providerTeam['name'] ?? 'Equipo');
        $code = $this->resolveTeamCode($providerTeam, $participant);
        $name = $this->translateCountryName($providerName, $code);
        $team = $externalId
            ? Team::query()->where('external_team_id', $externalId)->first()
            : null;

        if (! $team && $code !== '') {
            $team = Team::query()->where('code', $code)->first();
        }

        if (! $team) {
            $team = Team::query()
                ->where('name', $name)
                ->orWhere('name', $providerName)
                ->first();
        }

        $payload = array_filter([
            'external_team_id' => $externalId,
            'external_country_id' => $participant['country_id'] ?? $providerTeam['country_id'] ?? null,
            'name' => $name,
            'code' => $code,
            'group_label' => $this->resolveGroupLabel($participant ?? $providerTeam, $group),
            'provider_logo_url' => $providerTeam['logo'] ?? $participant['logo'] ?? null,
            'provider_flag_path' => $participant['flag'] ?? null,
            'flag_emoji' => $this->resolveFlagEmoji($participant['fifa_code'] ?? null, $providerName),
            'is_active' => true,
        ], fn ($value) => $value !== null && $value !== '');

        if ($team) {
            $team->update($payload);
            return $team->fresh();
        }

        return Team::query()->create($payload);
    }

    private function resolveKickoff(array $item): string
    {
        $date = $item['date'] ?? $item['fixture_date'] ?? null;
        $time = (string) ($item['time'] ?? $item['scheduled'] ?? '00:00:00');

        if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
            $normalizedTime = preg_match('/^\d{2}:\d{2}$/', $time) ? $time.':00' : $time;
            return Carbon::createFromFormat('Y-m-d H:i:s', trim($date.' '.$normalizedTime), 'UTC')->toDateTimeString();
        }

        return now('UTC')->toDateTimeString();
    }

    private function parseScore(?string $score): array
    {
        if (! $score || ! str_contains($score, '-')) {
            return [null, null];
        }

        [$home, $away] = array_map('trim', explode('-', $score, 2));
        return [is_numeric($home) ? (int) $home : null, is_numeric($away) ? (int) $away : null];
    }

    private function mapProviderStatus(?string $providerStatus): string
    {
        $providerStatus = Str::upper((string) $providerStatus);

        return match (true) {
            str_contains($providerStatus, 'FINISHED'),
            str_contains($providerStatus, 'FT'),
            str_contains($providerStatus, 'AET'),
            str_contains($providerStatus, 'AP') => 'final',
            str_contains($providerStatus, 'IN PLAY'),
            str_contains($providerStatus, 'LIVE'),
            str_contains($providerStatus, 'HT') => 'locked',
            default => 'scheduled',
        };
    }

    private function nullableDate(mixed $value): ?Carbon
    {
        if (! $value || $value === '0') {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveTeamCode(array $providerTeam, ?array $participant): string
    {
        $codeCandidates = [
            $participant['fifa_code'] ?? null,
            $participant['code'] ?? null,
            $providerTeam['fifa_code'] ?? null,
            $providerTeam['code'] ?? null,
        ];

        foreach ($codeCandidates as $code) {
            $normalized = strtoupper((string) $code);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return Str::upper(Str::limit(preg_replace('/[^A-Za-z0-9]/', '', (string) ($providerTeam['name'] ?? 'TEAM')) ?: 'TEAM', 20, ''));
    }

    private function resolveGroupLabel(array $item, ?array $group): ?string
    {
        $groupName = $item['group_name'] ?? $item['group'] ?? $group['name'] ?? null;
        if (! $groupName) {
            return null;
        }

        return Str::upper(Str::replaceStart('GROUP ', '', trim((string) $groupName)));
    }

    private function resolveStageLabel(array $item, ?array $group): ?string
    {
        return $this->stringOrNull($item['stage'] ?? $group['stage'] ?? null);
    }

    public function refreshFavoriteSidesFromRankings(): void
    {
        TournamentMatch::query()
            ->with(['homeTeam', 'awayTeam'])
            ->chunkById(100, function (Collection $matches): void {
                foreach ($matches as $match) {
                    if (! $match->homeTeam || ! $match->awayTeam) {
                        continue;
                    }

                    $match->update([
                        'favorite_side' => $this->resolveFavoriteSide(
                            $match->homeTeam,
                            $match->awayTeam,
                            is_array($match->raw_provider_payload) ? $match->raw_provider_payload : [],
                        ),
                    ]);
                }
            });
    }

    private function resolveFavoriteSide(Team $homeTeam, Team $awayTeam, array $item = []): string
    {
        $homeRanking = $homeTeam->frozen_ranking_fifa ?: $homeTeam->ranking_fifa;
        $awayRanking = $awayTeam->frozen_ranking_fifa ?: $awayTeam->ranking_fifa;

        if ($homeRanking && $awayRanking) {
            if ($homeRanking === $awayRanking) {
                return 'none';
            }

            return $homeRanking < $awayRanking ? 'home' : 'away';
        }

        if ($homeRanking && ! $awayRanking) {
            return 'home';
        }

        if ($awayRanking && ! $homeRanking) {
            return 'away';
        }

        return 'none';
    }

    private function stringOrNull(mixed $value): ?string
    {
        $normalized = trim((string) $value);
        return $normalized !== '' ? $normalized : null;
    }

    private function translateCountryName(mixed $name, ?string $fifaCode = null): ?string
    {
        $normalizedName = $this->stringOrNull($name);
        if (! $normalizedName) {
            return null;
        }

        $normalizedCode = strtoupper((string) $fifaCode);
        if ($normalizedCode !== '' && isset($this->countryNamesByFifaCode()[$normalizedCode])) {
            return $this->countryNamesByFifaCode()[$normalizedCode];
        }

        return $this->countryNamesByEnglishName()[Str::lower($normalizedName)] ?? $normalizedName;
    }

    private function resolveFlagEmoji(?string $fifaCode, string $teamName): ?string
    {
        $alpha2 = $this->fifaToAlpha2()[$fifaCode] ?? $this->nameToAlpha2()[$teamName] ?? null;
        if (! $alpha2) {
            return null;
        }

        $alpha2 = strtoupper($alpha2);
        $chars = str_split($alpha2);
        if (count($chars) !== 2) {
            return null;
        }

        return mb_chr(ord($chars[0]) + 127397).mb_chr(ord($chars[1]) + 127397);
    }

    private function fifaToAlpha2(): array
    {
        return [
            'ALG' => 'DZ',
            'ARG' => 'AR',
            'AUS' => 'AU',
            'AUT' => 'AT',
            'BEL' => 'BE',
            'BRA' => 'BR',
            'CAN' => 'CA',
            'CIV' => 'CI',
            'COL' => 'CO',
            'CPV' => 'CV',
            'CRC' => 'CR',
            'CRO' => 'HR',
            'CUW' => 'CW',
            'ECU' => 'EC',
            'EGY' => 'EG',
            'ENG' => 'GB',
            'ESP' => 'ES',
            'FRA' => 'FR',
            'GHA' => 'GH',
            'GER' => 'DE',
            'HAI' => 'HT',
            'IRN' => 'IR',
            'JOR' => 'JO',
            'JPN' => 'JP',
            'KOR' => 'KR',
            'MAR' => 'MA',
            'MEX' => 'MX',
            'NED' => 'NL',
            'NOR' => 'NO',
            'NZL' => 'NZ',
            'PAN' => 'PA',
            'PAR' => 'PY',
            'POR' => 'PT',
            'QAT' => 'QA',
            'KSA' => 'SA',
            'SCO' => 'GB',
            'SEN' => 'SN',
            'SUI' => 'CH',
            'RSA' => 'ZA',
            'TUN' => 'TN',
            'URU' => 'UY',
            'USA' => 'US',
            'UZB' => 'UZ',
        ];
    }

    private function nameToAlpha2(): array
    {
        return [
            'Algeria' => 'DZ',
            'Argentina' => 'AR',
            'Australia' => 'AU',
            'Austria' => 'AT',
            'Belgium' => 'BE',
            'Brazil' => 'BR',
            'Canada' => 'CA',
            'Cape Verde' => 'CV',
            'Colombia' => 'CO',
            'Croatia' => 'HR',
            'Curacao' => 'CW',
            'Ecuador' => 'EC',
            'Egypt' => 'EG',
            'England' => 'GB',
            'France' => 'FR',
            'Germany' => 'DE',
            'Ghana' => 'GH',
            'Haiti' => 'HT',
            'Iran' => 'IR',
            'Ivory Coast' => 'CI',
            'Japan' => 'JP',
            'Jordan' => 'JO',
            'Mexico' => 'MX',
            'Morocco' => 'MA',
            'Netherlands' => 'NL',
            'New Zealand' => 'NZ',
            'Norway' => 'NO',
            'Panama' => 'PA',
            'Paraguay' => 'PY',
            'Portugal' => 'PT',
            'Qatar' => 'QA',
            'Republic of Korea' => 'KR',
            'Saudi Arabia' => 'SA',
            'Scotland' => 'GB',
            'Senegal' => 'SN',
            'South Africa' => 'ZA',
            'Spain' => 'ES',
            'Switzerland' => 'CH',
            'Tunisia' => 'TN',
            'Uruguay' => 'UY',
            'USA' => 'US',
            'Uzbekistan' => 'UZ',
        ];
    }

    private function countryNamesByFifaCode(): array
    {
        return [
            'ALG' => 'Argelia',
            'ARG' => 'Argentina',
            'AUS' => 'Australia',
            'AUT' => 'Austria',
            'BEL' => 'Bélgica',
            'BRA' => 'Brasil',
            'CAN' => 'Canadá',
            'CIV' => 'Costa de Marfil',
            'COL' => 'Colombia',
            'CPV' => 'Cabo Verde',
            'CRC' => 'Costa Rica',
            'CRO' => 'Croacia',
            'CUW' => 'Curazao',
            'ECU' => 'Ecuador',
            'EGY' => 'Egipto',
            'ENG' => 'Inglaterra',
            'ESP' => 'España',
            'FRA' => 'Francia',
            'GER' => 'Alemania',
            'GHA' => 'Ghana',
            'HAI' => 'Haití',
            'IRN' => 'Irán',
            'JOR' => 'Jordania',
            'JPN' => 'Japón',
            'KOR' => 'Corea del Sur',
            'KSA' => 'Arabia Saudita',
            'MAR' => 'Marruecos',
            'MEX' => 'México',
            'NED' => 'Países Bajos',
            'NOR' => 'Noruega',
            'NZL' => 'Nueva Zelanda',
            'PAN' => 'Panamá',
            'PAR' => 'Paraguay',
            'POR' => 'Portugal',
            'QAT' => 'Catar',
            'RSA' => 'Sudáfrica',
            'SCO' => 'Escocia',
            'SEN' => 'Senegal',
            'SUI' => 'Suiza',
            'TUN' => 'Túnez',
            'URU' => 'Uruguay',
            'USA' => 'Estados Unidos',
            'UZB' => 'Uzbekistán',
        ];
    }

    private function countryNamesByEnglishName(): array
    {
        return [
            'algeria' => 'Argelia',
            'argentina' => 'Argentina',
            'australia' => 'Australia',
            'austria' => 'Austria',
            'belgium' => 'Bélgica',
            'brazil' => 'Brasil',
            'canada' => 'Canadá',
            'cape verde' => 'Cabo Verde',
            'colombia' => 'Colombia',
            'costa rica' => 'Costa Rica',
            'croatia' => 'Croacia',
            'curacao' => 'Curazao',
            'côte d’ivoire' => 'Costa de Marfil',
            'côte d\'ivoire' => 'Costa de Marfil',
            'ecuador' => 'Ecuador',
            'egypt' => 'Egipto',
            'england' => 'Inglaterra',
            'france' => 'Francia',
            'germany' => 'Alemania',
            'ghana' => 'Ghana',
            'haiti' => 'Haití',
            'iran' => 'Irán',
            'ir iran' => 'Irán',
            'ivory coast' => 'Costa de Marfil',
            'japan' => 'Japón',
            'jordan' => 'Jordania',
            'korea republic' => 'Corea del Sur',
            'mexico' => 'México',
            'morocco' => 'Marruecos',
            'netherlands' => 'Países Bajos',
            'new zealand' => 'Nueva Zelanda',
            'norway' => 'Noruega',
            'panama' => 'Panamá',
            'paraguay' => 'Paraguay',
            'portugal' => 'Portugal',
            'qatar' => 'Catar',
            'republic of korea' => 'Corea del Sur',
            'saudi arabia' => 'Arabia Saudita',
            'scotland' => 'Escocia',
            'senegal' => 'Senegal',
            'south africa' => 'Sudáfrica',
            'spain' => 'España',
            'switzerland' => 'Suiza',
            'tunisia' => 'Túnez',
            'united states' => 'Estados Unidos',
            'united states of america' => 'Estados Unidos',
            'uruguay' => 'Uruguay',
            'usa' => 'Estados Unidos',
            'uzbekistan' => 'Uzbekistán',
        ];
    }
}
