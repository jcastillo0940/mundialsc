<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MatchPrediction;
use App\Models\TournamentMatch;
use App\Models\TournamentPhase;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PredictionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => MatchPrediction::query()
                ->with(['match.homeTeam', 'match.awayTeam', 'match.phase'])
                ->where('user_id', $request->user()->id)
                ->latest('id')
                ->get(),
        ]);
    }

    public function store(Request $request, TournamentMatch $match): JsonResponse
    {
        if ($request->user()->disqualified_at) {
            throw ValidationException::withMessages([
                'account' => 'Tu cuenta fue descalificada y no puede seguir enviando pronosticos.',
            ]);
        }

        $match->loadMissing('phase');

        if ($match->phase?->slug !== 'fase-grupos') {
            throw ValidationException::withMessages([
                'match' => 'La promo esta habilitada solo para la fase de grupos.',
            ]);
        }

        if ($match->phase?->ends_at && now()->greaterThan($match->phase->ends_at)) {
            throw ValidationException::withMessages([
                'match' => 'El periodo para enviar pronosticos ya cerro.',
            ]);
        }

        if ($match->status !== 'scheduled' || $match->kickoff_at->isPast()) {
            throw ValidationException::withMessages([
                'match' => 'El partido ya esta cerrado para pronosticos.',
            ]);
        }

        $data = $request->validate([
            'predicted_home_score' => ['required', 'integer', 'min:0', 'max:20'],
            'predicted_away_score' => ['required', 'integer', 'min:0', 'max:20'],
        ]);

        $prediction = MatchPrediction::query()->updateOrCreate(
            [
                'match_id' => $match->id,
                'user_id' => $request->user()->id,
            ],
            [
                'phase_id' => $match->phase_id,
                'predicted_home_score' => $data['predicted_home_score'],
                'predicted_away_score' => $data['predicted_away_score'],
            ],
        );

        $this->markPredictionsCompletedIfNeeded($request->user()->id);

        return response()->json([
            'message' => 'Pronostico guardado.',
            'prediction' => $prediction->load(['match.homeTeam', 'match.awayTeam']),
        ], 201);
    }

    private function markPredictionsCompletedIfNeeded(int $userId): void
    {
        $groupStageId = TournamentPhase::query()
            ->where('slug', 'fase-grupos')
            ->value('id');

        if (! $groupStageId) {
            return;
        }

        $matchesCount = TournamentMatch::query()
            ->where('phase_id', $groupStageId)
            ->count();

        if ($matchesCount === 0) {
            return;
        }

        $predictionsCount = MatchPrediction::query()
            ->where('user_id', $userId)
            ->where('phase_id', $groupStageId)
            ->count();

        if ($predictionsCount < $matchesCount) {
            return;
        }

        User::query()
            ->whereKey($userId)
            ->whereNull('predictions_completed_at')
            ->update(['predictions_completed_at' => now()]);
    }
}
