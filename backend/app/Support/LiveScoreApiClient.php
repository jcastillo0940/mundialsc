<?php

namespace App\Support;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

class LiveScoreApiClient
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {
    }

    public function fixtures(array $params = []): array
    {
        return $this->paginate('fixtures/list.json', $params, ['fixtures', 'match', 'data']);
    }

    public function live(array $params = []): array
    {
        return $this->extractList($this->request('matches/live.json', $params), ['match', 'matches', 'data']);
    }

    public function commentary(int $matchId, array $params = []): array
    {
        return $this->extractList($this->request('matches/commentary.json', array_merge($params, [
            'match_id' => $matchId,
        ])), ['commentary']);
    }

    public function participants(array $params = []): array
    {
        return $this->extractList($this->request('competitions/participants.json', $params), [
            'participants',
            'teams',
            'data',
        ]);
    }

    public function competitionGroups(array $params = []): array
    {
        return $this->extractList($this->request('competitions/groups.json', $params), [
            'groups',
            'data',
        ]);
    }

    public function countryFlag(?int $teamId = null, ?int $countryId = null): Response
    {
        if (! $teamId && ! $countryId) {
            throw new RuntimeException('Se requiere team_id o country_id para obtener la bandera.');
        }

        $response = $this->http()
            ->accept('*/*')
            ->get('countries/flag.json', array_filter([
                'team_id' => $teamId,
                'country_id' => $countryId,
            ], fn ($value) => $value !== null && $value !== ''));

        if ($response->failed()) {
            throw new RuntimeException('No fue posible obtener la bandera desde Live Score API.');
        }

        return $response;
    }

    private function request(string $path, array $params = []): array
    {
        $response = $this->http()->acceptJson()->get($path, $params);

        if ($response->failed()) {
            throw new RuntimeException('Live Score API respondio con error HTTP '.$response->status().'.');
        }

        $payload = $response->json();

        if (! is_array($payload) || ($payload['success'] ?? true) !== true) {
            $message = $payload['error'] ?? 'Live Score API devolvio una respuesta invalida.';
            throw new RuntimeException((string) $message);
        }

        return $payload;
    }

    private function paginate(string $path, array $params, array $candidateKeys): array
    {
        $page = 1;
        $items = [];

        do {
            $payload = $this->request($path, array_merge($params, ['page' => $page]));
            $chunk = $this->extractList($payload, $candidateKeys);
            $items = [...$items, ...$chunk];
            $nextPage = Arr::get($payload, 'data.next_page');
            $page++;
        } while ($nextPage && ! empty($chunk));

        return $items;
    }

    private function extractList(array $payload, array $candidateKeys): array
    {
        $data = $payload['data'] ?? [];

        if (is_array($data) && array_is_list($data)) {
            return $data;
        }

        foreach ($candidateKeys as $key) {
            $value = Arr::get($data, $key);
            if (is_array($value) && array_is_list($value)) {
                return $value;
            }
        }

        foreach ($data as $value) {
            if (is_array($value) && array_is_list($value)) {
                return $value;
            }
        }

        return [];
    }

    private function http(): PendingRequest
    {
        $key = config('services.live_score.key');
        $secret = config('services.live_score.secret');

        if (! $key || ! $secret) {
            throw new RuntimeException('Faltan LIVE_SCORE_API_KEY o LIVE_SCORE_API_SECRET.');
        }

        return $this->http
            ->baseUrl(Str::finish((string) config('services.live_score.base_url'), '/'))
            ->timeout(20)
            ->withQueryParameters([
                'key' => $key,
                'secret' => $secret,
            ]);
    }
}
