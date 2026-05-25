<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LiveScoreCommentaryEvent;
use App\Models\TournamentMatch;
use Illuminate\Http\JsonResponse;

class MatchCommentaryController extends Controller
{
    public function index(TournamentMatch $match): JsonResponse
    {
        return response()->json([
            'data' => LiveScoreCommentaryEvent::query()
                ->where('tournament_match_id', $match->id)
                ->orderBy('match_second')
                ->get(),
        ]);
    }
}
