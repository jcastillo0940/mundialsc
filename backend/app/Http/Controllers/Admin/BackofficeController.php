<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyInvoiceGoal;
use App\Models\FraudFlag;
use App\Models\InvoiceGoalSetting;
use App\Models\LiveScoreCommentaryEvent;
use App\Models\LiveScoreSetting;
use App\Models\LiveScoreSyncRun;
use App\Models\MatchPrediction;
use App\Models\MatchResultApproval;
use App\Models\PhasePrize;
use App\Models\NewsletterSubscriber;
use App\Models\PrizeToken;
use App\Models\PromoWinner;
use App\Models\PromoWinnerContact;
use App\Models\MailLog;
use App\Models\AuditLog;
use App\Models\RegisteredInvoice;
use App\Models\SiteSetting;
use App\Models\Team;
use App\Models\TournamentMatch;
use App\Models\TournamentPhase;
use App\Models\User;
use App\Notifications\NewsletterConfirmationNotification;
use App\Support\Audit;
use App\Support\ContestInvoiceRegistrationService;
use App\Support\LiveScoreSyncService;
use App\Support\PointsAuditService;
use App\Support\PromotionRankingService;
use App\Support\TournamentScoring;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackofficeController extends Controller
{
    public function __construct(
        private readonly TournamentScoring $scoring,
        private readonly LiveScoreSyncService $liveScoreSync,
        private readonly PromotionRankingService $rankingService,
        private readonly PointsAuditService $pointsAuditService,
        private readonly ContestInvoiceRegistrationService $invoiceRegistrationService,
    ) {
    }

    public function dashboard(): View
    {
        return view('admin.dashboard', [
            'phases' => TournamentPhase::query()->orderBy('stage_order')->get(),
            'matchesCount' => TournamentMatch::query()->count(),
            'predictionsCount' => MatchPrediction::query()->count(),
            'invoiceGoalsCount' => RegisteredInvoice::query()->count(),
            'winnersCount' => PromoWinner::query()->whereIn('status', ['selected', 'contacting', 'confirmed', 'delivered'])->count(),
            'usersCount' => User::query()->where('role', 'client')->count(),
            'disqualifiedUsersCount' => User::query()->whereNotNull('disqualified_at')->count(),
        ]);
    }

    public function matches(Request $request): View
    {
        $phaseId = $request->integer('phase_id');
        $teamId = $request->integer('team_id');

        return view('admin.matches', [
            'matches' => TournamentMatch::query()
                ->with(['phase', 'homeTeam', 'awayTeam'])
                ->whereHas('phase', fn ($query) => $query->where('is_active', true))
                ->when($phaseId, fn ($query) => $query->where('phase_id', $phaseId))
                ->when($teamId, function ($query) use ($teamId): void {
                    $query->where(function ($teamQuery) use ($teamId): void {
                        $teamQuery
                            ->where('home_team_id', $teamId)
                            ->orWhere('away_team_id', $teamId);
                    });
                })
                ->orderBy('kickoff_at')
                ->get(),
            'pendingApprovals' => MatchResultApproval::query()
                ->with(['match.homeTeam', 'match.awayTeam', 'proposer'])
                ->where('status', 'pending')
                ->latest('id')
                ->get(),
            'phases' => TournamentPhase::query()->where('is_active', true)->orderBy('stage_order')->get(),
            'teams' => Team::query()->where('is_active', true)->orderBy('name')->get(),
            'filters' => [
                'phase_id' => $phaseId ?: '',
                'team_id' => $teamId ?: '',
            ],
        ]);
    }

    public function teams(): View
    {
        return view('admin.teams', [
            'teams' => Team::query()
                ->where('is_active', true)
                ->whereNotNull('group_label')
                ->orderBy('group_label')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function importTeamRankings(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:512'],
        ]);

        $path = $request->file('csv_file')->getRealPath();
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        $header = array_map(fn($h) => strtolower(trim($h)), $header);
        $codeIndex = array_search('code', $header);
        $rankingIndex = array_search('ranking_fifa', $header);

        if ($codeIndex === false || $rankingIndex === false) {
            fclose($handle);
            return back()->withErrors(['csv_file' => 'El CSV debe tener columnas: code, ranking_fifa']);
        }

        $teams = Team::query()->whereNotNull('group_label')->get()->keyBy(fn($t) => strtoupper(trim($t->code)));
        $updated = 0;
        $skipped = [];

        while (($row = fgetcsv($handle)) !== false) {
            $code = strtoupper(trim($row[$codeIndex] ?? ''));
            $ranking = isset($row[$rankingIndex]) ? (int) $row[$rankingIndex] : null;

            if (! $code || ! $ranking || $ranking < 1 || $ranking > 999) {
                continue;
            }

            if ($teams->has($code)) {
                $payload = ['ranking_fifa' => $ranking];
                if (! $teams[$code]->frozen_ranking_fifa) {
                    $payload['frozen_ranking_fifa'] = $ranking;
                    $payload['ranking_frozen_at'] = now();
                }
                $teams[$code]->update($payload);
                $updated++;
            } else {
                $skipped[] = $code;
            }
        }

        fclose($handle);

        $this->liveScoreSync->refreshFavoriteSidesFromRankings();

        $msg = "Ranking FIFA actualizado: {$updated} equipos.";
        if ($skipped) {
            $msg .= ' No encontrados: '.implode(', ', $skipped).'.';
        }

        return back()->with('status', $msg);
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
            'home_team_id' => ['required', 'exists:teams,id', 'different:away_team_id'],
            'away_team_id' => ['required', 'exists:teams,id'],
            'home_score' => ['nullable', 'integer', 'min:0', 'max:20'],
            'away_score' => ['nullable', 'integer', 'min:0', 'max:20'],
            'status' => ['required', 'in:scheduled,locked,final,void'],
            'favorite_side' => ['required', 'in:home,away,none'],
        ]);

        if ($data['status'] === 'final') {
            $match->update([
                'home_team_id' => $data['home_team_id'],
                'away_team_id' => $data['away_team_id'],
                'favorite_side' => $data['favorite_side'],
            ]);
            $this->liveScoreSync->refreshFavoriteSidesFromRankings();

            if ($data['home_score'] === null || $data['away_score'] === null) {
                throw ValidationException::withMessages([
                    'score' => 'Debes ingresar ambos marcadores para solicitar finalizacion.',
                ]);
            }

            return $this->requestMatchResultApproval($request, $match, (int) $data['home_score'], (int) $data['away_score']);
        }

        $match->update($data);
        $this->liveScoreSync->refreshFavoriteSidesFromRankings();

        return back()->with('status', 'Partido actualizado.');
    }

    public function finalizeMatch(Request $request, TournamentMatch $match): RedirectResponse
    {
        $data = $request->validate([
            'home_score' => ['required', 'integer', 'min:0', 'max:20'],
            'away_score' => ['required', 'integer', 'min:0', 'max:20'],
        ]);

        return $this->requestMatchResultApproval($request, $match, (int) $data['home_score'], (int) $data['away_score']);
    }
    public function approveMatchResult(Request $request, MatchResultApproval $approval): RedirectResponse
    {
        if ($approval->status !== 'pending') {
            return back()->withErrors(['approval' => 'Esta solicitud ya fue procesada.']);
        }

        if ((int) $approval->proposed_by_user_id === (int) $request->user()->id) {
            return back()->withErrors(['approval' => 'La regla de 4 ojos requiere que otro administrador apruebe el marcador.']);
        }

        $match = $approval->match()->with('phase')->firstOrFail();
        $match->update([
            'home_score' => $approval->home_score,
            'away_score' => $approval->away_score,
            'status' => 'final',
        ]);

        $this->ensureOfficialFavoriteForFinal($match->fresh('phase'));
        $this->scoring->recalculateForMatch($match->fresh('phase', 'predictions'));
        $affected = $match->predictions()->count();

        $approval->update([
            'status' => 'approved',
            'approved_by_user_id' => $request->user()->id,
            'approved_at' => now(),
        ]);

        Audit::log('match.result.approved', 'match_result_approval', $approval->id, $request->user(), $request, [
            'match_id' => $match->id,
            'home_score' => $approval->home_score,
            'away_score' => $approval->away_score,
        ]);

        return back()->with('status', "Marcador aprobado con regla de 4 ojos. Puntos recalculados para {$affected} prediccion(es).");
    }

    public function voidMatch(Request $request, TournamentMatch $match): RedirectResponse
    {
        $match->update([
            'home_score' => null,
            'away_score' => null,
            'status' => 'void',
        ]);

        MatchPrediction::query()
            ->where('match_id', $match->id)
            ->update([
                'points_awarded' => 0,
                'result_type' => 'void',
            ]);

        Audit::log('match.voided', 'tournament_match', $match->id, $request->user(), $request);

        return back()->with('status', 'Partido anulado. Todas las predicciones de ese encuentro quedaron en 0 puntos.');
    }

    public function rejectMatchResult(Request $request, MatchResultApproval $approval): RedirectResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $approval->update([
            'status' => 'rejected',
            'approved_by_user_id' => $request->user()->id,
            'approved_at' => now(),
            'notes' => $data['notes'] ?? null,
        ]);

        Audit::log('match.result.rejected', 'match_result_approval', $approval->id, $request->user(), $request, [
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('status', 'Solicitud de marcador rechazada.');
    }

    public function recalculateAllScores(): RedirectResponse
    {
        $matches = TournamentMatch::query()
            ->with('phase', 'predictions')
            ->where('status', 'final')
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->get();

        foreach ($matches as $match) {
            $this->ensureOfficialFavoriteForFinal($match);
            $this->scoring->recalculateForMatch($match);
        }

        return back()->with('status', "Puntos recalculados para {$matches->count()} partido(s) final(es).");
    }

    public function updateTeamRanking(Request $request, Team $team): RedirectResponse
    {
        $data = $request->validate([
            'ranking_fifa' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        if (! $team->frozen_ranking_fifa && ! empty($data['ranking_fifa'])) {
            $data['frozen_ranking_fifa'] = $data['ranking_fifa'];
            $data['ranking_frozen_at'] = now();
        }

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

    public function pointsAudit(Request $request): View
    {
        $filters = [
            'query' => trim((string) $request->query('query', '')),
            'source' => (string) $request->query('source', 'all'),
            'phase_id' => (string) $request->query('phase_id', ''),
            'impact' => (string) $request->query('impact', 'all'),
            'rule_code' => trim((string) $request->query('rule_code', '')),
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
        ];

        $report = $this->pointsAuditService->report($filters);

        return view('admin.points-audit', [
            'rows' => $report['rows'],
            'summary' => $report['summary'],
            'filters' => $filters,
            'phases' => TournamentPhase::query()->orderBy('stage_order')->get(),
        ]);
    }

    public function pointsAuditExportCsv(Request $request): StreamedResponse
    {
        $filters = [
            'query' => trim((string) $request->query('query', '')),
            'source' => (string) $request->query('source', 'all'),
            'phase_id' => (string) $request->query('phase_id', ''),
            'impact' => (string) $request->query('impact', 'all'),
            'rule_code' => trim((string) $request->query('rule_code', '')),
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
        ];

        $report = $this->pointsAuditService->report($filters);
        $rows = $report['rows'];
        $filename = 'auditoria-puntos-' . now()->format('Y-m-d_H-i') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel

            fputcsv($out, [
                'Fecha', 'Participante', 'ID', 'Correo', 'Origen', 'Fase',
                'Goles (+/-)', 'Tiros (+/-)', 'Regla', 'Codigo regla', 'Referencia', 'Explicacion',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    optional($row['occurred_at'])->format('Y-m-d H:i:s') ?? '',
                    $row['user_name'] ?? '',
                    $row['user_id'] ?? '',
                    $row['user_email'] ?? '',
                    $row['source'] ?? '',
                    $row['phase_name'] ?? '',
                    ($row['goals_delta'] > 0 ? '+' : '') . $row['goals_delta'],
                    ($row['shots_delta'] > 0 ? '+' : '') . $row['shots_delta'],
                    $row['rule_label'] ?? '',
                    $row['rule_code'] ?? '',
                    $row['reference'] ?? '',
                    $row['reason'] ?? '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
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
            'validation_mode' => ['required', 'in:api'],
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
        $data = $this->validatePrize($request);

        PhasePrize::query()->create($data);

        return back()->with('status', 'Premio registrado.');
    }

    public function updatePrize(Request $request, PhasePrize $prize): RedirectResponse
    {
        $prize->update($this->validatePrize($request));

        Audit::log('phase_prize.updated', 'phase_prize', $prize->id, $request->user(), $request, [
            'phase_id' => $prize->phase_id,
            'stock' => $prize->stock,
        ]);

        return back()->with('status', 'Premio actualizado.');
    }

    public function destroyPrize(Request $request, PhasePrize $prize): RedirectResponse
    {
        Audit::log('phase_prize.deleted', 'phase_prize', $prize->id, $request->user(), $request, [
            'phase_id' => $prize->phase_id,
            'title' => $prize->prize_title,
        ]);

        $prize->delete();

        return back()->with('status', 'Premio eliminado.');
    }

    public function winners(): View
    {
        $phaseId = request()->integer('phase_id') ?: null;
        $phase = $this->rankingService->activeRankingPhase($phaseId);
        $winnerSlots = $phase ? $this->rankingService->winnerSlotsForPhase($phase->id) : $this->rankingService->winnerSlots();
        $leaderboard = $phase ? $this->rankingService->leaderboardForPhase($phase->id, $winnerSlots)->all() : [];
        $winners = $phase
            ? PromoWinner::query()
                ->with(['user', 'contacts', 'prizeToken'])
                ->where('phase_id', $phase->id)
                ->orderBy('leaderboard_position')
                ->orderBy('id')
                ->get()
            : collect();
        $prizeTokens = $phase
            ? PrizeToken::query()
                ->where('phase_id', $phase->id)
                ->orderBy('token_code')
                ->get()
            : collect();

        $excludedUserIds = $winners->pluck('user_id')->all();
        $activeWinnersCount = $winners->whereIn('status', ['selected', 'contacting', 'confirmed', 'delivered'])->count();
        $remainingSlots = max($winnerSlots - $activeWinnersCount, 0);
        $tieContext = $phase && $remainingSlots > 0
            ? $this->rankingService->tieContextForPhase($phase->id, $remainingSlots, $excludedUserIds)
            : ['requires_draw' => false, 'auto_selected' => [], 'tied_candidates' => [], 'remaining_slots' => 0];

        return view('admin.winners', [
            'phase' => $phase,
            'phases' => TournamentPhase::query()->orderBy('stage_order')->get(),
            'leaderboard' => $leaderboard,
            'winners' => $winners,
            'winnerSlots' => $winnerSlots,
            'prizeTokens' => $prizeTokens,
            'tieContext' => $tieContext,
        ]);
    }

    public function winnersActa(): View
    {
        $phaseId = request()->integer('phase_id') ?: null;
        $phase = $this->rankingService->activeRankingPhase($phaseId);
        $winners = $phase
            ? PromoWinner::query()
                ->with(['user', 'prizeToken'])
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
        $winner->loadMissing(['phase', 'user', 'contacts', 'prizeToken']);

        return view('admin.winner-communications-acta', [
            'winner' => $winner,
            'generatedAt' => now(),
        ]);
    }

    public function generateWinners(Request $request): RedirectResponse
    {
        $phaseId = $request->integer('phase_id') ?: null;
        $phase = $this->rankingService->activeRankingPhase($phaseId);
        abort_if(! $phase, 404);

        $existingActive = PromoWinner::query()
            ->where('phase_id', $phase->id)
            ->whereIn('status', ['selected', 'contacting', 'confirmed', 'delivered'])
            ->exists();

        if ($existingActive) {
            return back()->with('status', 'Ya existe una selección activa de ganadores para esta fase.');
        }

        $winnerSlots = $this->rankingService->winnerSlotsForPhase($phase->id);
        $this->ensurePrizeTokensForPhase($phase->id, $winnerSlots);
        $rows = $this->rankingService->leaderboardForPhase($phase->id, $winnerSlots);

        DB::transaction(function () use ($rows, $phase, $request): void {
            foreach ($rows as $row) {
                $this->createWinnerFromRow($phase->id, $row, 'rank', $request->user()->id);
            }
        });

        Audit::log('promo.winners.generated', 'promo_winner', null, $request->user(), $request, [
            'phase_id' => $phase->id,
            'auto_selected_count' => $rows->count(),
            'limit' => $winnerSlots,
        ]);

        return back()->with('status', "Ganadores iniciales generados con limite estricto de {$winnerSlots} token(es) de premio.");
    }

    public function resolveDraw(Request $request): RedirectResponse
    {
        throw ValidationException::withMessages([
            'draw' => 'El sorteo manual esta deshabilitado. El sistema resuelve empates por cascada y timestamp de servidor.',
        ]);
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
        if ($winner->status === 'delivered' || $winner->prize_delivered_at) {
            return back()->withErrors(['winner' => 'Este premio ya fue entregado y la cuenta esta bloqueada para nuevos premios.']);
        }


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

    public function deliverWinner(Request $request, PromoWinner $winner): RedirectResponse
    {
        DB::transaction(function () use ($winner, $request): void {
            $winner->loadMissing('prizeToken');

            if (! $winner->prizeToken) {
                throw ValidationException::withMessages([
                    'prize_token' => 'Este ganador no tiene token de premio asignado.',
                ]);
            }

            if (PromoWinner::query()
                ->where('user_id', $winner->user_id)
                ->where('id', '!=', $winner->id)
                ->where(function ($query): void {
                    $query->where('status', 'delivered')->orWhereNotNull('prize_delivered_at');
                })
                ->exists()) {
                throw ValidationException::withMessages([
                    'winner' => 'Esta cedula/pasaporte ya tiene un premio entregado.',
                ]);
            }

            $winner->update([
                'status' => 'delivered',
                'responded_at' => $winner->responded_at ?? now(),
                'prize_delivered_at' => now(),
            ]);

            $winner->prizeToken->update([
                'status' => 'delivered',
                'current_promo_winner_id' => $winner->id,
                'assigned_user_id' => $winner->user_id,
                'delivered_at' => now(),
            ]);

            PromoWinnerContact::query()->create([
                'promo_winner_id' => $winner->id,
                'contact_type' => 'other',
                'contact_status' => 'confirmed',
                'contacted_at' => now(),
                'notes' => 'Premio entregado. Cuenta bloqueada para recibir otro premio.',
                'created_by' => $request->user()->id,
            ]);

            Audit::log('promo.winner.prize_delivered', 'promo_winner', $winner->id, $request->user(), $request, [
                'prize_token_id' => $winner->prize_token_id,
                'token_code' => $winner->prizeToken->token_code,
            ]);
        });

        return back()->with('status', 'Premio entregado. La cuenta queda bloqueada para recibir otro premio.');
    }

    public function updateWinner(Request $request, PromoWinner $winner): RedirectResponse
    {
        $data = $request->validate([
            'leaderboard_position' => ['required', 'integer', 'min:1'],
            'total_points' => ['required', 'numeric', 'min:0'],
            'exact_hits' => ['required', 'integer', 'min:0'],
            'invoice_count' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:selected,contacting,confirmed,delivered,disqualified'],
            'selection_reason' => ['required', 'in:rank,draw,replacement,manual'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['status'] === 'delivered' && PromoWinner::query()
            ->where('user_id', $winner->user_id)
            ->where('id', '!=', $winner->id)
            ->where(function ($query): void {
                $query->where('status', 'delivered')->orWhereNotNull('prize_delivered_at');
            })
            ->exists()) {
            throw ValidationException::withMessages([
                'winner' => 'Esta cedula/pasaporte ya tiene un premio entregado.',
            ]);
        }

        $winner->update([
            'leaderboard_position' => $data['leaderboard_position'],
            'total_points' => $data['total_points'],
            'exact_hits' => $data['exact_hits'],
            'invoice_count' => $data['invoice_count'],
            'status' => $data['status'],
            'selection_reason' => $data['selection_reason'],
            'notes' => $data['notes'] ?? null,
            'disqualified_at' => $data['status'] === 'disqualified' ? ($winner->disqualified_at ?? now()) : null,
            'responded_at' => in_array($data['status'], ['confirmed', 'delivered'], true) ? ($winner->responded_at ?? now()) : $winner->responded_at,
            'prize_delivered_at' => $data['status'] === 'delivered' ? ($winner->prize_delivered_at ?? now()) : $winner->prize_delivered_at,
        ]);

        if ($data['status'] === 'delivered') {
            $winner->loadMissing('prizeToken');
            $winner->prizeToken?->update([
                'status' => 'delivered',
                'current_promo_winner_id' => $winner->id,
                'assigned_user_id' => $winner->user_id,
                'delivered_at' => $winner->prize_delivered_at ?? now(),
            ]);
        }

        Audit::log('promo.winner.updated', 'promo_winner', $winner->id, $request->user(), $request, Arr::only($data, [
            'leaderboard_position',
            'total_points',
            'exact_hits',
            'invoice_count',
            'status',
            'selection_reason',
        ]));

        return back()->with('status', 'Ganador actualizado manualmente.');
    }

    public function disqualifyWinner(Request $request, PromoWinner $winner): RedirectResponse
    {
        if ($winner->status === 'delivered' || $winner->prize_delivered_at) {
            throw ValidationException::withMessages([
                'winner' => 'No se puede reasignar un premio ya entregado.',
            ]);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $replacement = DB::transaction(function () use ($winner, $request, $data): ?array {
            $winner->loadMissing('prizeToken');

            $winner->update([
                'status' => 'disqualified',
                'prize_token_id' => null,
                'notes' => $data['reason'],
                'disqualified_at' => now(),
            ]);

            $winner->prizeToken?->update([
                'status' => 'reassigned',
                'current_promo_winner_id' => null,
                'reassigned_from_promo_winner_id' => $winner->id,
            ]);

            PromoWinnerContact::query()->create([
                'promo_winner_id' => $winner->id,
                'contact_type' => 'other',
                'contact_status' => 'discarded',
                'contacted_at' => now(),
                'notes' => $data['reason'],
                'created_by' => $request->user()->id,
            ]);

            return $this->promoteNextWinner($winner, $request->user()->id, $winner->prizeToken);
        });

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

    public function users(): View
    {
        $query = trim((string) request('query'));

        return view('admin.users', [
            'users' => User::query()
                ->where('role', 'client')
                ->when($query !== '', fn ($builder) => $builder->where(function ($search) use ($query) {
                    $search->where('name', 'like', '%'.$query.'%')
                        ->orWhere('cedula', 'like', '%'.$query.'%')
                        ->orWhere('email', 'like', '%'.$query.'%');
                }))
                ->orderByDesc('created_at')
                ->limit(200)
                ->get(),
            'query' => $query,
        ]);
    }

    public function editUser(User $user): View
    {
        abort_unless($user->role === 'client', 404);

        return view('admin.user-edit', [
            'user' => $user,
            'branches' => \App\Models\Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function userAudit(User $user): View
    {
        abort_unless($user->role === 'client', 404);

        $events = AuditLog::query()
            ->where(function ($query) use ($user) {
                $query->where('entity_type', 'user')
                    ->where('entity_id', $user->id)
                    ->orWhere(function ($nested) use ($user) {
                        $nested->where('entity_type', 'user')
                            ->whereNull('entity_id')
                            ->where('user_id', $user->id);
                    })
                    ->orWhere(function ($nested) use ($user) {
                        $nested->where('entity_type', 'registered_invoice')
                            ->where('entity_id', '>', 0)
                            ->whereRaw("json_extract(payload, '$.participant_user_id') = ?", [$user->id]);
                    })
                    ->orWhere(function ($nested) use ($user) {
                        $nested->where('entity_type', 'fraud_flag')
                            ->where('entity_id', '>', 0)
                            ->whereRaw("json_extract(payload, '$.user_id') = ?", [$user->id]);
                    })
                    ->orWhere(function ($nested) use ($user) {
                        $nested->where('entity_type', 'coupon')
                            ->where('entity_id', '>', 0)
                            ->whereRaw("json_extract(payload, '$.user_id') = ?", [$user->id]);
                    });
            })
            ->latest('id')
            ->paginate(80)
            ->withQueryString();

        return view('admin.user-audit', [
            'user' => $user,
            'events' => $events,
        ]);
    }

    public function newsletter(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $status = $request->string('status', 'all')->toString();

        $subscribersQuery = NewsletterSubscriber::query()
            ->when($search !== '', fn ($query) => $query->where('email', 'like', '%'.$search.'%'))
            ->when($status === 'confirmed', fn ($query) => $query->whereNotNull('confirmed_at')->whereNull('unsubscribed_at'))
            ->when($status === 'pending', fn ($query) => $query->whereNull('confirmed_at')->whereNull('unsubscribed_at'))
            ->when($status === 'unsubscribed', fn ($query) => $query->whereNotNull('unsubscribed_at'));

        return view('admin.newsletter', [
            'subscribers' => $subscribersQuery->orderByDesc('updated_at')->paginate(40)->withQueryString(),
            'summary' => [
                'total' => NewsletterSubscriber::query()->count(),
                'confirmed' => NewsletterSubscriber::query()->whereNotNull('confirmed_at')->whereNull('unsubscribed_at')->count(),
                'pending' => NewsletterSubscriber::query()->whereNull('confirmed_at')->whereNull('unsubscribed_at')->count(),
                'unsubscribed' => NewsletterSubscriber::query()->whereNotNull('unsubscribed_at')->count(),
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function newsletterResendConfirmation(Request $request, NewsletterSubscriber $subscriber): RedirectResponse
    {
        if ($subscriber->unsubscribed_at) {
            return back()->withErrors(['subscriber' => 'Este correo ya se dio de baja.']);
        }

        $token = Str::random(64);

        $subscriber->forceFill([
            'subscribed_at' => $subscriber->subscribed_at ?? now(),
            'confirmed_at' => null,
            'confirmation_token_hash' => Hash::make($token),
            'confirmation_sent_at' => now(),
            'source' => $subscriber->source ?: 'admin',
        ])->save();

        $subscriber->notify(new \App\Notifications\NewsletterConfirmationNotification($token));

        MailLog::query()->create([
            'channel' => 'newsletter',
            'event_type' => 'resent',
            'recipient_email' => $subscriber->email,
            'subject' => 'Confirma tu suscripción al newsletter',
            'status' => 'resent',
            'subscriber_id' => $subscriber->id,
            'user_id' => $request->user()?->id,
            'meta' => [
                'action' => 'admin_resend',
            ],
            'sent_at' => now(),
        ]);

        Audit::log('newsletter.confirmation_resent', 'newsletter_subscriber', $subscriber->id, $request->user(), $request);

        return back()->with('status', 'Se reenviÃ³ la confirmaciÃ³n del newsletter.');
    }

    public function newsletterUnsubscribe(Request $request, NewsletterSubscriber $subscriber): RedirectResponse
    {
        $subscriber->forceFill([
            'unsubscribed_at' => now(),
            'confirmed_at' => null,
            'confirmation_token_hash' => null,
            'confirmation_sent_at' => null,
        ])->save();

        MailLog::query()->create([
            'channel' => 'newsletter',
            'event_type' => 'unsubscribed',
            'recipient_email' => $subscriber->email,
            'subject' => 'Newsletter baja',
            'status' => 'unsubscribed',
            'subscriber_id' => $subscriber->id,
            'user_id' => $request->user()?->id,
            'meta' => [
                'action' => 'admin_unsubscribe',
            ],
            'sent_at' => now(),
        ]);

        Audit::log('newsletter.unsubscribed', 'newsletter_subscriber', $subscriber->id, $request->user(), $request);

        return back()->with('status', 'Suscriptor dado de baja.');
    }

    public function newsletterPreview(): View
    {
        $resetPreviewHtml = view('emails.reset-password', [
            'name' => 'Participante Demo',
            'url' => route('admin.dashboard'),
            'appName' => config('app.name', 'Super Carnes'),
            'supportEmail' => config('mail.from.address'),
        ])->render();

        $newsletterPreviewHtml = view('emails.newsletter-confirmation', [
            'name' => 'Amigo Demo',
            'url' => route('admin.dashboard'),
            'appName' => config('app.name', 'Super Carnes'),
            'supportEmail' => config('mail.from.address'),
        ])->render();

        return view('admin.newsletter-preview', [
            'resetPreviewHtml' => $resetPreviewHtml,
            'newsletterPreviewHtml' => $newsletterPreviewHtml,
        ]);
    }

    public function mailLogs(Request $request): View
    {
        $channel = $request->string('channel', 'all')->toString();
        $status = $request->string('status', 'all')->toString();
        $search = trim((string) $request->string('search'));

        $query = MailLog::query()
            ->when($channel !== 'all', fn ($q) => $q->where('channel', $channel))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where('recipient_email', 'like', '%'.$search.'%'));

        return view('admin.mail-logs', [
            'logs' => $query->latest('id')->paginate(60)->withQueryString(),
            'summary' => [
                'total' => MailLog::query()->count(),
                'queued' => MailLog::query()->where('status', 'queued')->count(),
                'confirmed' => MailLog::query()->where('status', 'confirmed')->count(),
                'resent' => MailLog::query()->where('status', 'resent')->count(),
                'unsubscribed' => MailLog::query()->where('status', 'unsubscribed')->count(),
            ],
            'filters' => compact('channel', 'status', 'search'),
        ]);
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $before = [
            'name' => $user->name,
            'cedula' => $user->cedula,
            'document_type' => $user->document_type,
            'email' => $user->email,
            'phone' => $user->phone,
            'branch_id' => $user->branch_id,
            'birthdate' => optional($user->birthdate)?->toDateString(),
            'resides_in_panama' => (bool) $user->resides_in_panama,
            'is_employee' => (bool) $user->is_employee,
            'is_active' => (bool) $user->is_active,
            'disqualified_at' => optional($user->disqualified_at)?->toIso8601String(),
            'disqualification_reason' => $user->disqualification_reason,
        ];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'cedula' => ['required', 'string', 'max:30', 'unique:users,cedula,'.$user->id],
            'document_type' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'birthdate' => ['nullable', 'date'],
            'resides_in_panama' => ['required', 'boolean'],
            'is_employee' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'disqualify_user' => ['required', 'boolean'],
            'disqualification_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $shouldDisqualify = (bool) $data['disqualify_user'];
        $reason = $data['disqualification_reason'] ?? null;

        $payload = [
            'name' => $data['name'],
            'cedula' => $data['cedula'],
            'document_type' => $data['document_type'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?: null,
            'branch_id' => $data['branch_id'] ?: null,
            'birthdate' => $data['birthdate'] ?: null,
            'resides_in_panama' => (bool) $data['resides_in_panama'],
            'is_employee' => (bool) $data['is_employee'],
            'is_active' => (bool) $data['is_active'],
            'disqualified_at' => $shouldDisqualify ? ($user->disqualified_at ?? now()) : null,
            'disqualification_reason' => $shouldDisqualify ? $reason : null,
        ];

        if (! empty($data['password'])) {
            $payload['password'] = bcrypt($data['password']);
        }

        $user->update($payload);

        $after = [
            'name' => $user->name,
            'cedula' => $user->cedula,
            'document_type' => $user->document_type,
            'email' => $user->email,
            'phone' => $user->phone,
            'branch_id' => $user->branch_id,
            'birthdate' => optional($user->birthdate)?->toDateString(),
            'resides_in_panama' => (bool) $user->resides_in_panama,
            'is_employee' => (bool) $user->is_employee,
            'is_active' => (bool) $user->is_active,
            'disqualified_at' => optional($user->disqualified_at)?->toIso8601String(),
            'disqualification_reason' => $user->disqualification_reason,
        ];

        Audit::log('user.profile.updated', 'user', $user->id, $request->user(), $request, [
            'before' => $before,
            'after' => $after,
            'changes' => collect($before)
                ->mapWithKeys(fn ($value, $key) => $value === $after[$key] ? [] : [$key => ['before' => $value, 'after' => $after[$key]]])
                ->all(),
        ]);

        Audit::log('user.status.updated', 'user', $user->id, $request->user(), $request, [
            'is_active' => $user->is_active,
            'disqualified_at' => optional($user->disqualified_at)?->toIso8601String(),
            'disqualification_reason' => $user->disqualification_reason,
        ]);

        return back()->with('status', 'Participante actualizado.');
    }

    public function fraud(): View
    {
        return view('admin.fraud', [
            'flags' => FraudFlag::query()
                ->with(['user', 'invoice', 'reviewer'])
                ->latest('id')
                ->limit(200)
                ->get(),
            'summary' => [
                'open' => FraudFlag::query()->whereIn('status', ['open', 'reviewing'])->count(),
                'critical' => FraudFlag::query()->where('severity', 'critical')->whereIn('status', ['open', 'reviewing'])->count(),
                'resolved' => FraudFlag::query()->whereIn('status', ['resolved', 'dismissed'])->count(),
            ],
        ]);
    }

    public function updateFraudFlag(Request $request, FraudFlag $flag): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:open,reviewing,resolved,dismissed'],
            'resolution_notes' => ['nullable', 'string', 'max:1500'],
            'disqualify_user' => ['required', 'boolean'],
        ]);

        $flag->update([
            'status' => $data['status'],
            'resolution_notes' => $data['resolution_notes'] ?? null,
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        if ((bool) $data['disqualify_user']) {
            $flag->user?->update([
                'is_active' => false,
                'disqualified_at' => $flag->user->disqualified_at ?? now(),
                'disqualification_reason' => $data['resolution_notes'] ?: $flag->title,
            ]);
        }

        Audit::log('fraud.flag.reviewed', 'fraud_flag', $flag->id, $request->user(), $request, [
            'status' => $flag->status,
            'disqualified_user' => (bool) $data['disqualify_user'],
        ]);

        return back()->with('status', 'Caso antifraude actualizado.');
    }

    public function exportFraudFlags(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="fraud-flags.csv"',
        ];

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'fecha', 'estado', 'severidad', 'tipo', 'usuario', 'cedula', 'email', 'cufe', 'titulo', 'descripcion']);

            FraudFlag::query()
                ->with(['user', 'invoice'])
                ->orderBy('id')
                ->chunk(200, function ($flags) use ($handle): void {
                    foreach ($flags as $flag) {
                        fputcsv($handle, [
                            $flag->id,
                            optional($flag->created_at)?->toDateTimeString(),
                            $flag->status,
                            $flag->severity,
                            $flag->flag_type,
                            $flag->user?->name,
                            $flag->user?->cedula,
                            $flag->user?->email,
                            $flag->invoice?->cufe,
                            $flag->title,
                            $flag->description,
                        ]);
                    }
                });

            fclose($handle);
        }, 'fraud-flags.csv', $headers);
    }

    public function storeAssistedInvoice(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'qr_raw_text' => ['required', 'string', 'max:2048'],
            'branch_id' => ['nullable', 'integer'],
            'fraud_flag_id' => ['nullable', 'integer', 'exists:fraud_flags,id'],
            'assistance_notes' => ['required', 'string', 'max:1500'],
        ]);

        $flag = null;

        if (! empty($data['fraud_flag_id'])) {
            $flag = FraudFlag::query()->findOrFail((int) $data['fraud_flag_id']);

            if ((int) $flag->user_id !== (int) $user->id) {
                throw ValidationException::withMessages([
                    'fraud_flag_id' => 'El caso antifraude no corresponde al participante seleccionado.',
                ]);
            }
        }

        $result = $this->invoiceRegistrationService->registerAssisted(
            targetUser: $user,
            data: $data,
            actor: $request->user(),
            request: $request,
            fraudFlag: $flag,
        );

        $verificationStatus = $result['verification_status'] ?? null;
        $pointsAwarded = $verificationStatus === 'approved' ? 1 : 0;
        $verificationLabel = match ($verificationStatus) {
            'approved' => 'aprobada',
            'pending' => 'pendiente de confirmacion',
            'disqualify' => 'en revision antifraude',
            'rejected' => 'no aprobada',
            default => 'procesada',
        };

        return back()->with('status', "Factura asistida {$verificationLabel}. Puntos acreditados: {$pointsAwarded}. ".($result['message'] ?? ''));
    }

    public function site(): View
    {
        return view('admin.site', [
            'settings' => $this->siteSettings(),
        ]);
    }

    public function branches(): View
    {
        return view('admin.branches', [
            'branches' => \App\Models\Branch::query()->orderBy('name')->get(),
        ]);
    }

    public function storeBranch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40', 'unique:branches,code', 'regex:/^[A-Z0-9_]+$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        \App\Models\Branch::query()->create(array_merge($data, ['is_active' => true]));

        return back()->with('status', 'Sucursal creada.');
    }

    public function updateBranch(Request $request, \App\Models\Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:120'],
            'address'   => ['nullable', 'string', 'max:255'],
            'phone'     => ['nullable', 'string', 'max:30'],
            'is_active' => ['required', 'boolean'],
        ]);

        $branch->update($data);

        return back()->with('status', 'Sucursal actualizada.');
    }

    public function updateSiteSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'auth_bg_youtube_id' => ['nullable', 'string', 'max:20'],
            'auth_logo_url' => ['nullable', 'url', 'max:255'],
            'header_logo_url' => ['nullable', 'url', 'max:255'],
            'participant_brands' => ['nullable', 'string'],
            'hero_video_url' => ['nullable', 'url', 'max:255'],
            'seo_site_title' => ['nullable', 'string', 'max:120'],
            'seo_meta_description' => ['nullable', 'string', 'max:255'],
            'seo_meta_keywords' => ['nullable', 'string', 'max:255'],
            'seo_og_title' => ['nullable', 'string', 'max:120'],
            'seo_og_description' => ['nullable', 'string', 'max:255'],
            'seo_og_image' => ['nullable', 'url', 'max:255'],
            'terms_and_conditions' => ['nullable', 'string'],
            'show_scanner_debug'   => ['nullable', 'boolean'],
            'show_auth_ticker'     => ['nullable', 'boolean'],
            'contact_email'        => ['nullable', 'email', 'max:120'],
            'contact_phone'        => ['nullable', 'string', 'max:40'],
            'contact_address'      => ['nullable', 'string', 'max:255'],
            'contact_hours'        => ['nullable', 'string', 'max:255'],
            'theme_background'     => ['nullable', 'string', 'max:20'],
            'theme_surface_low'    => ['nullable', 'string', 'max:20'],
            'theme_surface'        => ['nullable', 'string', 'max:20'],
            'theme_surface_high'   => ['nullable', 'string', 'max:20'],
            'theme_primary'        => ['nullable', 'string', 'max:20'],
            'theme_secondary'      => ['nullable', 'string', 'max:20'],
            'theme_text_main'      => ['nullable', 'string', 'max:20'],
            'theme_outline_variant' => ['nullable', 'string', 'max:20'],
            'recaptcha_enabled'    => ['nullable', 'boolean'],
            'recaptcha_site_key'   => ['nullable', 'string', 'max:255'],
            'recaptcha_secret_key' => ['nullable', 'string', 'max:255'],
            'mail_mailer'          => ['nullable', 'in:smtp,log,array,sendmail,failover,roundrobin,postmark,resend,ses'],
            'mail_host'            => ['nullable', 'string', 'max:255'],
            'mail_port'            => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username'        => ['nullable', 'string', 'max:255'],
            'mail_password'        => ['nullable', 'string', 'max:255'],
            'mail_encryption'      => ['nullable', 'in:tls,ssl'],
            'mail_from_address'    => ['nullable', 'email', 'max:150'],
            'mail_from_name'       => ['nullable', 'string', 'max:150'],
        ]);

        $data['show_scanner_debug'] = $request->boolean('show_scanner_debug') ? '1' : '0';
        $data['show_auth_ticker']   = $request->boolean('show_auth_ticker') ? '1' : '0';
        $data['recaptcha_enabled']  = $request->boolean('recaptcha_enabled') ? '1' : '0';
        $data['mail_port']          = isset($data['mail_port']) ? (string) $data['mail_port'] : null;
        $data['mail_encryption']    = $data['mail_encryption'] === '' ? null : ($data['mail_encryption'] ?? null);

        foreach ($data as $key => $value) {
            SiteSetting::set($key, $value);
        }

        Audit::log('site.settings.updated', 'site_setting', null, $request->user(), $request, [
            'updated_keys' => array_keys($data),
        ]);

        return back()->with('status', 'Configuracion del sitio actualizada.');
    }

    public function testSmtp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'test_email' => ['required', 'email', 'max:150'],
        ]);

        $recipient = trim($data['test_email']);
        $subject = 'Prueba SMTP - '.config('app.name', 'Laravel');
        $html = view('emails.smtp-test', [
            'appName' => config('app.name', 'Super Carnes'),
            'recipient' => $recipient,
            'siteUrl' => route('admin.dashboard'),
        ])->render();

        try {
            Mail::html($html, function ($message) use ($recipient, $subject): void {
                $message->to($recipient)->subject($subject);
            });
        } catch (\Throwable $throwable) {
            Audit::log('mail.smtp_test_failed', 'mail_test', null, $request->user(), $request, [
                'recipient_email' => $recipient,
                'error' => $throwable->getMessage(),
            ]);

            return back()->withErrors(['test_email' => 'No se pudo enviar el correo de prueba. Revisa la configuracion SMTP.']);
        }

        MailLog::query()->create([
            'channel' => 'smtp_test',
            'event_type' => 'sent',
            'recipient_email' => $recipient,
            'subject' => $subject,
            'status' => 'sent',
            'user_id' => $request->user()?->id,
            'meta' => [
                'action' => 'smtp_test',
            ],
            'sent_at' => now(),
        ]);

        Audit::log('mail.smtp_test_sent', 'mail_test', null, $request->user(), $request, [
            'recipient_email' => $recipient,
        ]);

        return back()->with('status', 'Correo de prueba enviado correctamente.');
    }

    private function validatePrize(Request $request): array
    {
        return $request->validate([
            'phase_id' => ['required', 'exists:tournament_phases,id'],
            'ranking_from' => ['required', 'integer', 'min:1'],
            'ranking_to' => ['required', 'integer', 'min:1', 'gte:ranking_from'],
            'football_role' => ['required', 'string', 'max:80'],
            'prize_title' => ['required', 'string', 'max:150'],
            'prize_type' => ['required', 'string', 'max:120'],
            'stock' => ['required', 'integer', 'min:0'],
        ]);
    }

    private function siteSettings(): array
    {
        $keys = [
            'auth_bg_youtube_id',
            'auth_logo_url',
            'header_logo_url',
            'participant_brands',
            'hero_video_url',
            'seo_site_title',
            'seo_meta_description',
            'seo_meta_keywords',
            'seo_og_title',
            'seo_og_description',
            'seo_og_image',
            'terms_and_conditions',
            'show_scanner_debug',
            'show_auth_ticker',
            'contact_email',
            'contact_phone',
            'contact_address',
            'contact_hours',
            'theme_background',
            'theme_surface_low',
            'theme_surface',
            'theme_surface_high',
            'theme_primary',
            'theme_secondary',
            'theme_text_main',
            'theme_outline_variant',
            'recaptcha_enabled',
            'recaptcha_site_key',
            'recaptcha_secret_key',
            'mail_mailer',
            'mail_host',
            'mail_port',
            'mail_username',
            'mail_password',
            'mail_encryption',
            'mail_from_address',
            'mail_from_name',
        ];

        $settings = [];

        foreach ($keys as $key) {
            $settings[$key] = SiteSetting::get($key, '');
        }

        return $settings;
    }

    private function createWinnerFromRow(
        int $phaseId,
        array $row,
        string $reason,
        ?int $createdBy = null,
        ?int $replacementForWinnerId = null,
        ?PrizeToken $forcedToken = null,
    ): PromoWinner
    {
        $token = $forcedToken ?? $this->nextAvailablePrizeToken($phaseId, (int) $row['position']);

        if (! $token) {
            throw ValidationException::withMessages([
                'prize_inventory' => 'No hay tokens fisicos de premio disponibles para asignar mas ganadores.',
            ]);
        }

        $winner = PromoWinner::query()->updateOrCreate(
            [
                'phase_id' => $phaseId,
                'user_id' => $row['user_id'],
            ],
            [
                'prize_token_id' => $token->id,
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

        $token->update([
            'status' => 'awaiting_claim',
            'current_promo_winner_id' => $winner->id,
            'assigned_user_id' => $winner->user_id,
            'assigned_at' => now(),
            'reassigned_from_promo_winner_id' => $replacementForWinnerId,
        ]);

        return $winner;
    }

    private function ensurePrizeTokensForPhase(int $phaseId, int $requiredTokens): void
    {
        $existingTokens = PrizeToken::query()->where('phase_id', $phaseId)->count();

        if ($existingTokens >= $requiredTokens) {
            return;
        }

        $phasePrizes = PhasePrize::query()
            ->where('phase_id', $phaseId)
            ->orderBy('ranking_from')
            ->get();

        if ($phasePrizes->isEmpty()) {
            for ($index = $existingTokens + 1; $index <= $requiredTokens; $index++) {
                PrizeToken::query()->firstOrCreate(
                    ['token_code' => sprintf('FASE_%d_PREMIO_TV_%d', $phaseId, $index)],
                    [
                        'phase_id' => $phaseId,
                        'prize_title' => 'Televisor',
                        'prize_type' => 'TV',
                        'status' => 'available',
                    ],
                );
            }

            return;
        }

        foreach ($phasePrizes as $phasePrize) {
            for ($index = 1; $index <= max((int) $phasePrize->stock, 0); $index++) {
                PrizeToken::query()->firstOrCreate(
                    ['token_code' => $this->prizeTokenCode($phasePrize, $index)],
                    [
                        'phase_id' => $phaseId,
                        'phase_prize_id' => $phasePrize->id,
                        'prize_title' => $phasePrize->prize_title,
                        'prize_type' => $phasePrize->prize_type,
                        'status' => 'available',
                    ],
                );
            }
        }

        if (PrizeToken::query()->where('phase_id', $phaseId)->count() < $requiredTokens) {
            throw ValidationException::withMessages([
                'prize_inventory' => "El inventario fisico no alcanza el maximo de {$requiredTokens} premio(s).",
            ]);
        }
    }

    private function nextAvailablePrizeToken(int $phaseId, int $leaderboardPosition): ?PrizeToken
    {
        $phasePrizeIds = PhasePrize::query()
            ->where('phase_id', $phaseId)
            ->where('ranking_from', '<=', $leaderboardPosition)
            ->where('ranking_to', '>=', $leaderboardPosition)
            ->pluck('id')
            ->all();

        return PrizeToken::query()
            ->where('phase_id', $phaseId)
            ->where('status', 'available')
            ->when($phasePrizeIds !== [], fn ($query) => $query->whereIn('phase_prize_id', $phasePrizeIds))
            ->orderBy('token_code')
            ->lockForUpdate()
            ->first()
            ?? PrizeToken::query()
                ->where('phase_id', $phaseId)
                ->where('status', 'available')
                ->orderBy('token_code')
                ->lockForUpdate()
                ->first();
    }

    private function prizeTokenCode(PhasePrize $phasePrize, int $index): string
    {
        $base = Str::upper(Str::slug($phasePrize->prize_type ?: $phasePrize->prize_title, '_')) ?: 'PREMIO';

        return sprintf('FASE_%d_%s_%d', $phasePrize->phase_id, $base, $index);
    }

    private function requestMatchResultApproval(Request $request, TournamentMatch $match, int $homeScore, int $awayScore): RedirectResponse
    {
        $candidate = $match->replicate();
        $candidate->home_score = $homeScore;
        $candidate->away_score = $awayScore;
        $candidate->setRelation('phase', $match->phase);
        $this->ensureOfficialFavoriteForFinal($candidate->loadMissing('phase'));

        $approval = MatchResultApproval::query()->create([
            'tournament_match_id' => $match->id,
            'proposed_by_user_id' => $request->user()->id,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'status' => 'pending',
        ]);

        Audit::log('match.result.proposed', 'match_result_approval', $approval->id, $request->user(), $request, [
            'match_id' => $match->id,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
        ]);

        return back()->with('status', 'Marcador enviado a aprobacion dual. Otro administrador debe aprobarlo para recalcular puntos.');
    }

    private function ensureOfficialFavoriteForFinal(TournamentMatch $match): void
    {
        if ($match->phase?->slug !== 'fase-grupos') {
            return;
        }

        if ($match->home_score === $match->away_score) {
            return;
        }

        if ($match->favorite_side !== 'none') {
            return;
        }

        throw ValidationException::withMessages([
            'favorite_side' => 'No se puede finalizar o recalcular un partido con ganador sin ranking FIFA oficial para ambos equipos.',
        ]);
    }

    private function promoteNextWinner(PromoWinner $winner, ?int $createdBy = null, ?PrizeToken $token = null): ?array
    {
        $excludedUserIds = PromoWinner::query()
            ->where('phase_id', $winner->phase_id)
            ->pluck('user_id')
            ->all();

        $next = $this->rankingService->nextEligibleCandidate($winner->phase_id, $excludedUserIds);

        if (! $next) {
            return null;
        }

        $this->createWinnerFromRow($winner->phase_id, $next, 'replacement', $createdBy, $winner->id, $token);

        return $next;
    }

    public function playerPoints(): View
    {
        $query   = trim((string) request('query'));
        $phaseId = request()->integer('phase_id') ?: null;
        $phases  = TournamentPhase::orderBy('stage_order')->get();

        $users = DB::table('users')
            ->leftJoin('wallets', 'wallets.user_id', '=', 'users.id')
            ->leftJoin('branches', 'branches.id', '=', 'users.branch_id')
            ->where('users.role', 'client')
            ->when($query, fn ($q) => $q->where(function ($w) use ($query) {
                $w->where('users.name', 'like', "%{$query}%")
                  ->orWhere('users.cedula', 'like', "%{$query}%")
                  ->orWhere('users.email', 'like', "%{$query}%");
            }))
            ->selectRaw("
                users.id,
                users.name,
                users.cedula,
                users.email,
                users.phone,
                users.disqualified_at,
                branches.name as branch_name,
                COALESCE(wallets.goals_balance, 0) as total_points,
                COALESCE(wallets.lifetime_goals_earned, 0) as lifetime_points
            ")
            ->orderByDesc('total_points')
            ->paginate(50)
            ->withQueryString();

        $userIds = collect($users->items())->pluck('id')->all();

        $invoiceSums = DB::table('registered_invoices')
            ->whereIn('user_id', $userIds)
            ->where('validation_status', 'approved')
            ->selectRaw('user_id, SUM(points_awarded) as pts, COUNT(*) as cnt')
            ->groupBy('user_id')
            ->pluck('pts', 'user_id');

        $invoiceCounts = DB::table('registered_invoices')
            ->whereIn('user_id', $userIds)
            ->where('validation_status', 'approved')
            ->selectRaw('user_id, COUNT(*) as cnt')
            ->groupBy('user_id')
            ->pluck('cnt', 'user_id');

        $predictionQuery = DB::table('match_predictions')
            ->whereIn('user_id', $userIds)
            ->where('points_awarded', '>', 0);

        if ($phaseId) {
            $predictionQuery->where('phase_id', $phaseId);
        }

        $predSums = $predictionQuery
            ->selectRaw('user_id, SUM(points_awarded) as pts, COUNT(*) as hits')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        return view('admin.player-points', compact(
            'users', 'query', 'phases', 'phaseId',
            'invoiceSums', 'invoiceCounts', 'predSums'
        ));
    }

    public function playerPointsDetail(User $user): View
    {
        $phases = TournamentPhase::orderBy('stage_order')->get()->keyBy('id');

        $invoices = RegisteredInvoice::where('user_id', $user->id)
            ->orderByDesc('issued_at')
            ->with(['registeredBy', 'assistedByFraudFlag'])
            ->get([
                'id',
                'invoice_number',
                'cufe',
                'issuer_name',
                'purchase_amount',
                'points_awarded',
                'issued_at',
                'branch_id',
                'validation_status',
                'registration_source',
                'registered_by_user_id',
                'assisted_by_fraud_flag_id',
                'assistance_notes',
                'created_at',
            ]);

        $predictions = MatchPrediction::with(['match.homeTeam', 'match.awayTeam', 'match.phase'])
            ->where('user_id', $user->id)
            ->where('points_awarded', '>', 0)
            ->orderByDesc('updated_at')
            ->get();

        $branches = \App\Models\Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $relatedFraudFlags = FraudFlag::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['open', 'reviewing'])
            ->latest('id')
            ->get(['id', 'title', 'flag_type', 'status']);

        $wallet = $user->wallet;

        $invoicePoints    = $invoices->where('validation_status', 'approved')->sum('points_awarded');
        $predictionPoints = $predictions->sum('points_awarded');

        return view('admin.player-points-detail', compact(
            'user', 'wallet', 'invoices', 'predictions',
            'invoicePoints', 'predictionPoints', 'phases', 'branches', 'relatedFraudFlags'
        ));
    }
}
