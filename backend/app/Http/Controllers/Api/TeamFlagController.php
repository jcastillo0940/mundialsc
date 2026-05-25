<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Support\LiveScoreApiClient;
use Illuminate\Http\Response as HttpResponse;

class TeamFlagController extends Controller
{
    public function show(Team $team, LiveScoreApiClient $client): HttpResponse
    {
        abort_unless($team->external_team_id || $team->external_country_id, 404);

        $response = $client->countryFlag($team->external_team_id, $team->external_country_id);

        return response($response->body(), 200, [
            'Content-Type' => $response->header('Content-Type', 'image/png'),
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
