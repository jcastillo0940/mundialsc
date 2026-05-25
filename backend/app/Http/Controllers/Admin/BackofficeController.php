<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyInvoiceGoal;
use App\Models\InvoiceGoalSetting;
use App\Models\LiveScoreCommentaryEvent;
use App\Models\LiveScoreSetting;
use App\Models\LiveScoreSyncRun;
use App\Models\MatchPrediction;
use App\Models\PhasePrize;
use App\Models\PromoWinner;
use App\Models\PromoWinnerContact;
use App\Models\RegisteredInvoice;
use App\Models\Team;
use App\Models\TournamentMatch;
use App\Models\TournamentPhase;
use App\Support\Audit;
use App\Support\LiveScoreSyncService;
use App\Support\PromotionRankingService;
use App\Support\TournamentScoring;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BackofficeController extends Controller
{
    public function __construct(
        private readonly TournamentScoring $scoring,
        private readonly LiveScoreSyncService $liveScoreSync,
        private readonly PromotionRankingService $rankingService,
    ) {
    }

    public function dashboard(): View
    {
        return view('admin.dashboard', [
            'phases' => TournamentPhase::query()->orderBy('stage_order')->get(),
            'matchesCount' => TournamentMatch::query()->count(),
            'predictionsCount' => MatchPrediction::query()->count(),
            'invoiceGoalsCount' => RegisteredInvoice::query()->count(),
            'winnersCount' => PromoWinner::query()->whereIn('status', ['selected', 'contacting', 'confirmed'])->count(),
        ]);
    }

    public function matches(): View
    {
        return view('admin.matches', [
            'matches' => TournamentMatch::query()->with(['phase', 'homeTeam', 'awayTeam'])->orderBy('kickoff_at')->get(),
            'phases' => TournamentPhase::query()->orderBy('stage_order')->get(),
            'teams' => Team::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function teams(): View
    {
        return view('admin.teams', [
            'teams' => Team::query()->where('is_active', true)->orderBy('group_label')->orderBy('name')->get(),
        ]);
    }

    public function storeMatch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phase_id' => ['required', 'exists:tournament_phases,id'],
            'match_number' => ['nullable', 'integer', 'min:1'],
            'group_label' => ['nullable', 'string', 'max:10'],
            'home_team_id' => ['required', 'exists:teams,id', 'different:away_team_id'],
            'away_team_id' => ['required', 'exists:teams,id'],
            'favorite_side' => ['required', 'in:home,away,none'],
            'kickoff_at' => ['required', 'date'],
        ]);

        TournamentMatch::query()->create($data);
        $this->liveScoreSync->refreshFavoriteSidesFromRankings();

        return back()->with('status', 'Partido creado.');
    }

    public function updateMatch(Request $request, TournamentMatch $match): RedirectResponse
    {
        $data = $request->validate([
            'home_score' => ['nullable', 'integer', 'min:0', 'max:20'],
            'away_score' => ['nullable', 'integer', 'min:0', 'max:20'],
            'status' => ['required', 'in:scheduled,locked,final'],
            'favorite_side' => ['required', 'in:home,away,none'],
        ]);

        $match->update($data);
        $this->liveScoreSync->refreshFavoriteSidesFromRankings();

        if ($match->status === 'final' && $match->home_score !== null && $match->away_score !== null) {
            $this->scoring->recalculateForMatch($match->fresh('phase', 'predictions'));
        }

        return back()->with('status', 'Partido actualizado.');
    }

    public function updateTeamRanking(Request $request, Team $team): RedirectResponse
    {
        $data = $request->validate([
            'ranking_fifa' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $team->update($data);
        $this->liveScoreSync->refreshFavoriteSidesFromRankings();

        return back()->with('status', 'Ranking FIFA actualizado para '.$team->name.'.');
    }

    public function rules(): View
    {
        return view('admin.rules', [
            'phases' => TournamentPhase::query()->orderBy('stage_order')->get(),
            'invoiceSettings' => InvoiceGoalSetting::query()->first(),
        ]);
    }

    public function updatePhase(Request $request, TournamentPhase $phase): RedirectResponse
    {
        $data = $request->validate([
            'exact_score_points' => ['required', 'integer', 'min:0'],
            'outcome_points' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ]);

        $phase->update($data);

        return back()->with('status', 'Reglas de fase actualizadas.');
    }

    public function updateInvoiceSettings(Request $request): RedirectResponse
    {
        $settings = InvoiceGoalSetting::query()->firstOrFail();

        $data = $request->validate([
            'is_enabled' => ['required', 'boolean'],
            'goal_value' => ['required', 'numeric', 'min:0'],
            'min_purchase_amount' => ['required', 'numeric', 'min:0'],
            'max_invoice_age_days' => ['required', 'integer', 'min:0', 'max:7'],
            'one_invoice_per_day' => ['required', 'boolean'],
            'validation_mode' => ['required', 'in:manual,external_db,api'],
        ]);

        $settings->update($data);

        return back()->with('status', 'Configuración de facturas actualizada.');
    }

    public function prizes(): View
    {
        return view('admin.prizes', [
            'phases' => TournamentPhase::query()->orderBy('stage_order')->get(),
            'prizes' => PhasePrize::query()->with('phase')->orderBy('phase_id')->orderBy('ranking_from')->get(),
        ]);
    }

    public function storePrize(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phase_id' => ['required', 'exists:tournament_phases,id'],
            'ranking_from' => ['required', 'integer', 'min:1'],
            'ranking_to' => ['required', 'integer', 'min:1'],
            'football_role' => ['required', 'string', 'max:80'],
            'prize_title' => ['required', 'string', 'max:150'],
            'prize_type' => ['required', 'string', 'max:120'],
            'stock' => ['required', 'integer', 'min:0'],
        ]);

        PhasePrize::query()->create($data);

        return back()->with('status', 'Premio registrado.');
    }

    public function winners(): View
    {
        $phase = $this->rankingService->groupStagePhase();
        $leaderboard = $phase ? $this->rankingService->leaderboardForPhase($phase->id)->all() : [];
        $winners = $phase
            ? PromoWinner::query()
                ->with(['user', 'contacts'])
                ->where('phase_id', $phase->id)
                ->orderBy('leaderboard_position')
                ->orderBy('id')
                ->get()
            : collect();

        $excludedUserIds = $winners->pluck('user_id')->all();
        $activeWinnersCount = $winners->whereIn('status', ['selected', 'contacting', 'confirmed'])->count();
        $remainingSlots = max(PromotionRankingService::WINNER_SLOTS - $activeWinnersCount, 0);
        $tieContext = $phase && $remainingSlots > 0
            ? $this->rankingService->tieContextForPhase($phase->id, $remainingSlots, $excludedUserIds)
            : ['requires_draw' => false, 'auto_selected' => [], 'tied_candidates' => [], 'remaining_slots' => 0];

        return view('admin.winners', [
            'phase' => $phase,
            'leaderboard' => $leaderboard,
            'winners' => $winners,
            'winnerSlots' => PromotionRankingService::WINNER_SLOTS,
            'tieContext' => $tieContext,
        ]);
    }

    public function winnersActa(): View
    {
        $phase = $this->rankingService->groupStagePhase();
        $winners = $phase
            ? PromoWinner::query()
                ->with('user')
                ->where('phase_id', $phase->id)
                ->orderBy('leaderboard_position')
                ->orderBy('id')
                ->get()
            : collect();

        return view('admin.winners-acta', [
            'phase' => $phase,
            'winners' => $winners,
            'generatedAt' => now(),
        ]);
    }

    public function winnerCommunicationsActa(PromoWinner $winner): View
    {
        $winner->loadMissing(['phase', 'user', 'contacts']);

        return view('admin.winner-communications-acta', [
            'winner' => $winner,
            'generatedAt' => now(),
        ]);
    }

    public function generateWinners(Request $request): RedirectResponse
    {
        $phase = $this->rankingService->groupStagePhase();
        abort_if(! $phase, 404);

        $existingActive = PromoWinner::query()
            ->where('phase_id', $phase->id)
            ->whereIn('status', ['selected', 'contacting', 'confirmed'])
            ->exists();

        if ($existingActive) {
            return back()->with('status', 'Ya existe una selección activa de ganadores para esta fase.');
        }

        $context = $this->rankingService->tieContextForPhase($phase->id, PromotionRankingService::WINNER_SLOTS);

        foreach ($context['auto_selected'] as $row) {
            $this->createWinnerFromRow($phase->id, $row, 'rank', $request->user()->id);
        }

        Audit::log('promo.winners.generated', 'promo_winner', null, $request->user(), $request, [
            'phase_id' => $phase->id,
            'auto_selected_count' => count($context['auto_selected']),
            'requires_draw' => $context['requires_draw'],
        ]);

        if ($context['requires_draw']) {
            return back()->with('status', 'Selección inicial creada. Hay empate técnico y debes resolverlo por sorteo.');
        }

        return back()->with('status', 'Ganadores iniciales generados.');
    }

    public function resolveDraw(Request $request): RedirectResponse
    {
        $phase = $this->rankingService->groupStagePhase();
        abort_if(! $phase, 404);

        $data = $request->validate([
            'slots' => ['required', 'integer', 'min:1', 'max:3'],
            'candidate_user_ids' => ['required', 'array', 'min:1'],
            'candidate_user_ids.*' => ['integer'],
        ]);

        $candidateIds = collect($data['candidate_user_ids'])->map(fn ($id) => (int) $id)->all();
        $excluded = PromoWinner::query()->where('phase_id', $phase->id)->pluck('user_id')->all();
        $eligibleRows = $this->rankingService->leaderboardForPhase($phase->id)
            ->filter(fn (array $row) => in_array($row['user_id'], $candidateIds, true) && ! in_array($row['user_id'], $excluded, true))
            ->values();

        if ($eligibleRows->count() < $data['slots']) {
            throw ValidationException::withMessages([
                'draw' => 'No hay suficientes candidatos elegibles para resolver el sorteo.',
            ]);
        }

        $selectedRows = $eligibleRows->shuffle()->take($data['slots']);

        DB::transaction(function () use ($selectedRows, $phase, $request): void {
            foreach ($selectedRows as $row) {
                $this->createWinnerFromRow($phase->id, $row, 'draw', $request->user()->id);
            }
        });

        Audit::log('promo.winners.draw_resolved', 'promo_winner', null, $request->user(), $request, [
            'phase_id' => $phase->id,
            'selected_user_ids' => $selectedRows->pluck('user_id')->all(),
        ]);

        return back()->with('status', 'Sorteo resuelto y ganadores asignados.');
    }

    public function logWinnerContact(Request $request, PromoWinner $winner): RedirectResponse
    {
        $data = $request->validate([
            'contact_type' => ['required', 'in:call,email,sms,whatsapp,other'],
            'contact_status' => ['required', 'in:attempted,answered,no_answer,sent,bounced,confirmed,discarded'],
            'contacted_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        PromoWinnerContact::query()->create([
            'promo_winner_id' => $winner->id,
            'contact_type' => $data['contact_type'],
            'contact_status' => $data['contact_status'],
            'contacted_at' => $data['contacted_at'],
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $winner->update([
            'status' => in_array($data['contact_status'], ['answered', 'confirmed'], true) ? 'contacting' : 'contacting',
            'last_contact_at' => $data['contacted_at'],
        ]);

        Audit::log('promo.winner.contact_logged', 'promo_winner', $winner->id, $request->user(), $request, Arr::only($data, [
            'contact_type',
            'contact_status',
            'contacted_at',
        ]));

        return back()->with('status', 'Gestión de contacto registrada.');
    }

    public function confirmWinner(Request $request, PromoWinner $winner): RedirectResponse
    {
        $winner->update([
            'status' => 'confirmed',
            'responded_at' => now(),
        ]);

        PromoWinnerContact::query()->create([
            'promo_winner_id' => $winner->id,
            'contact_type' => 'other',
            'contact_status' => 'confirmed',
            'contacted_at' => now(),
            'notes' => 'Ganador confirmado.',
            'created_by' => $request->user()->id,
        ]);

        Audit::log('promo.winner.confirmed', 'promo_winner', $winner->id, $request->user(), $request);

        return back()->with('status', 'Ganador confirmado.');
    }

    public function disqualifyWinner(Request $request, PromoWinner $winner): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $winner->update([
            'status' => 'disqualified',
            'notes' => $data['reason'],
            'disqualified_at' => now(),
        ]);

        PromoWinnerContact::query()->create([
            'promo_winner_id' => $winner->id,
            'contact_type' => 'other',
            'contact_status' => 'discarded',
            'contacted_at' => now(),
            'notes' => $data['reason'],
            'created_by' => $request->user()->id,
        ]);

        $replacement = $this->promoteNextWinner($winner, $request->user()->id);

        Audit::log('promo.winner.disqualified', 'promo_winner', $winner->id, $request->user(), $request, [
            'reason' => $data['reason'],
            'replacement_user_id' => $replacement['user_id'] ?? null,
        ]);

        return back()->with('status', $replacement
            ? 'Ganador descartado. Se promovió automáticamente a '.$replacement['full_name'].'.'
            : 'Ganador descartado. No hay más candidatos disponibles.');
    }

    public function integrations(): View
    {
        return view('admin.integrations', [
            'settings' => LiveScoreSetting::query()->first(),
            'runs' => LiveScoreSyncRun::query()->latest('id')->limit(20)->get(),
            'importedMatchesCount' => TournamentMatch::query()->where('provider', 'live_score_api')->count(),
            'commentaryEventsCount' => LiveScoreCommentaryEvent::query()->count(),
        ]);
    }

    public function updateIntegrationSettings(Request $request): RedirectResponse
    {
        $settings = LiveScoreSetting::query()->firstOrFail();

        $data = $request->validate([
            'is_enabled' => ['required', 'boolean'],
            'competition_id' => ['nullable', 'string', 'max:120'],
            'competition_ids' => ['nullable', 'string', 'max:255'],
            'season' => ['nullable', 'string', 'max:20'],
            'lang' => ['required', 'string', 'max:10'],
            'sync_from_date' => ['nullable', 'date'],
            'sync_to_date' => ['nullable', 'date'],
            'auto_sync_commentary' => ['required', 'boolean'],
        ]);

        $settings->update($data);

        return back()->with('status', 'Configuración Live Score API actualizada.');
    }

    public function syncFixtures(Request $request): RedirectResponse
    {
        $run = $this->liveScoreSync->syncFixtures([], $request->user()?->id);

        return back()->with('status', $run->status === 'completed'
            ? 'Sincronización de fixtures completada.'
            : 'Sincronización de fixtures falló: '.$run->error_message);
    }

    public function syncLive(Request $request): RedirectResponse
    {
        $run = $this->liveScoreSync->syncLive([], $request->user()?->id);

        return back()->with('status', $run->status === 'completed'
            ? 'Sincronización de partidos en vivo completada.'
            : 'Sincronización live falló: '.$run->error_message);
    }

    public function syncCommentary(Request $request): RedirectResponse
    {
        $run = $this->liveScoreSync->syncCommentary(null, [], $request->user()?->id);

        return back()->with('status', $run->status === 'completed'
            ? 'Sincronización de commentary completada.'
            : 'Sincronización commentary falló: '.$run->error_message);
    }

    private function createWinnerFromRow(int $phaseId, array $row, string $reason, ?int $createdBy = null, ?int $replacementForWinnerId = null): PromoWinner
    {
        return PromoWinner::query()->updateOrCreate(
            [
                'phase_id' => $phaseId,
                'user_id' => $row['user_id'],
            ],
            [
                'leaderboard_position' => $row['position'],
                'total_points' => $row['goals'],
                'exact_hits' => $row['exact_hits'],
                'invoice_count' => $row['invoice_count'],
                'invoice_total_amount' => $row['invoice_total_amount'] ?? 0,
                'goal_prediction_delta' => $row['goal_prediction_delta'] ?? null,
                'ranking_timestamp' => $row['ranking_timestamp'] ?? null,
                'selection_reason' => $reason,
                'status' => 'selected',
                'replacement_for_winner_id' => $replacementForWinnerId,
                'selected_at' => now(),
                'created_by' => $createdBy,
            ],
        );
    }

    private function promoteNextWinner(PromoWinner $winner, ?int $createdBy = null): ?array
    {
        $excludedUserIds = PromoWinner::query()
            ->where('phase_id', $winner->phase_id)
            ->pluck('user_id')
            ->all();

        $next = $this->rankingService->nextEligibleCandidate($winner->phase_id, $excludedUserIds);

        if (! $next) {
            return null;
        }

        $this->createWinnerFromRow($winner->phase_id, $next, 'replacement', $createdBy, $winner->id);

        return $next;
    }
}
