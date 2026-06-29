<?php

namespace App\Support;

use App\Models\FraudFlag;
use App\Models\MatchPrediction;
use App\Models\RegisteredInvoice;
use App\Models\TournamentMatch;
use App\Models\TournamentPhase;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SystemDiagnosticsService
{
    private const APPROVED_INVOICE_STATUSES = ['approved', 'manual_approved'];

    public function __construct(
        private readonly PromotionRankingService $rankingService,
    ) {
    }

    public function build(): array
    {
        $phase = $this->rankingService->activeRankingPhase()
            ?? TournamentPhase::query()->orderBy('stage_order')->first();

        return [
            'generated_at' => now(),
            'active_phase' => $phase,
            'participants' => $this->participants(),
            'invoices' => $this->invoices(),
            'predictions' => $predictions = $this->predictionBreakdown(),
            'points' => $phase ? $this->points($phase) : null,
            'top_ranking' => $phase ? $this->rankingService->fullRankedLeaderboard($phase->id)->take(10) : collect(),
            'fraud' => $this->fraud(),
        ];
    }

    public function toText(array $report): string
    {
        $lines = [];

        $lines[] = 'DIAGNOSTICO DEL SISTEMA - MUNDIAL SUPERCARNES';
        $lines[] = 'Generado: ' . $report['generated_at']->format('Y-m-d H:i');
        if ($report['active_phase']) {
            $lines[] = 'Fase activa de puntuacion: ' . $report['active_phase']->name;
        }
        $lines[] = str_repeat('=', 60);

        $p = $report['participants'];
        $lines[] = '';
        $lines[] = '1) PARTICIPANTES';
        $lines[] = '-' . str_repeat('-', 30);
        $lines[] = "Usuarios totales: {$p['total_users']} ({$p['admins']} administradores + {$p['clients']} clientes)";
        $lines[] = "Clientes activos: {$p['active_clients']} | Inactivos: {$p['inactive_clients']} | Descalificados: {$p['disqualified_clients']}";
        $lines[] = "Registro completado: {$p['registration_completed']} de {$p['clients']}";
        $lines[] = "Hicieron al menos una prediccion: {$p['clients_with_predictions']}";
        $lines[] = "No hicieron ninguna prediccion: {$p['clients_without_predictions']}";
        $lines[] = "Nunca han iniciado sesion registrada: {$p['clients_never_logged_in']}";

        $i = $report['invoices'];
        $lines[] = '';
        $lines[] = '2) FACTURAS REGISTRADAS';
        $lines[] = '-' . str_repeat('-', 30);
        $lines[] = "Total de facturas registradas: {$i['total']}";
        foreach ($i['by_validation_status'] as $status => $count) {
            $lines[] = "  - {$status}: {$count}";
        }
        $lines[] = 'Monto total de compras aprobadas: B/. ' . number_format($i['approved_amount'], 2);
        $lines[] = "Clientes con al menos 1 factura: {$i['users_with_invoices']} de {$p['clients']}";
        $lines[] = "Clientes sin ninguna factura: {$i['users_without_invoices']}";
        $lines[] = "Clientes con 1 a 4 facturas: {$i['users_with_1_to_4']}";
        $lines[] = "Clientes con mas de 4 facturas: {$i['users_with_5_or_more']}";
        $lines[] = "Maximo de facturas registradas por un mismo cliente: {$i['max_invoices_single_user']}";

        $pr = $report['predictions'];
        $lines[] = '';
        $lines[] = '3) PREDICCIONES DE PARTIDOS';
        $lines[] = '-' . str_repeat('-', 30);
        $lines[] = "Partidos finalizados: {$pr['finalized_matches']} | Partidos por jugarse: {$pr['scheduled_matches']}";
        $lines[] = "Predicciones evaluadas (partidos ya jugados): {$pr['graded_predictions']}";
        $lines[] = "Predicciones pendientes (partidos sin jugar): {$pr['pending_predictions']}";
        $lines[] = '';
        $lines[] = "  Marcador exacto acertado: {$pr['exact_total']}";
        $lines[] = "  Acertaron solo el resultado (sin marcador exacto): {$pr['outcome_total']}";
        $lines[] = "  Fallaron por completo: {$pr['miss_total']}";
        $lines[] = '';
        $lines[] = '  Detalle por tipo de partido:';
        foreach ($pr['categories'] as $key => $cat) {
            $lines[] = '    ' . $this->categoryLabel($key) . " ({$cat['matches']} partidos): exactos={$cat['exact']} | solo_resultado={$cat['outcome']} | fallos={$cat['miss']}";
        }

        if ($report['points']) {
            $pts = $report['points'];
            $lines[] = '';
            $lines[] = '4) PUNTOS OTORGADOS (fase: ' . $pts['phase_name'] . ')';
            $lines[] = '-' . str_repeat('-', 30);
            $lines[] = "Puntos por predicciones: {$pts['prediction_points_total']}";
            $lines[] = "Puntos por facturas: {$pts['invoice_points_total']}";
            $lines[] = 'Total de puntos repartidos: ' . ($pts['prediction_points_total'] + $pts['invoice_points_total']);
            $lines[] = "Clientes con puntos (> 0): {$pts['clients_with_points']}";
            $lines[] = "Clientes sin puntos (0): {$pts['clients_without_points']}";
        }

        if ($report['top_ranking']->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '5) TOP 10 DEL RANKING ACTUAL';
            $lines[] = '-' . str_repeat('-', 30);
            foreach ($report['top_ranking'] as $row) {
                $lines[] = "  {$row['position']}. {$row['full_name']} - {$row['total_points']} pts (pred={$row['prediction_points']} + fact={$row['invoice_points']}), exactos={$row['exact_hits']}, facturas={$row['invoice_count']}";
            }
        }

        $f = $report['fraud'];
        $lines[] = '';
        $lines[] = '6) ALERTAS ANTIFRAUDE (informativo)';
        $lines[] = '-' . str_repeat('-', 30);
        $lines[] = "Total de alertas generadas: {$f['total_flags']}";
        $lines[] = "Usuarios distintos con alguna alerta: {$f['distinct_users_flagged']}";
        foreach ($f['by_type'] as $type => $count) {
            $lines[] = "  - {$type}: {$count}";
        }

        return implode("\n", $lines) . "\n";
    }

    private function categoryLabel(string $key): string
    {
        return match ($key) {
            'favorite' => 'Gano el favorito',
            'underdog' => 'Gano el no favorito (sorpresa)',
            'draw' => 'Empate',
            default => 'Sin favorito definido',
        };
    }

    private function participants(): array
    {
        $clients = User::query()->where('role', 'client');

        $totalClients = (clone $clients)->count();
        $activeClients = (clone $clients)->where('is_active', true)->count();

        $predictedUserIds = MatchPrediction::query()->distinct()->pluck('user_id');

        return [
            'total_users' => User::count(),
            'admins' => User::where('role', 'admin')->count(),
            'clients' => $totalClients,
            'active_clients' => $activeClients,
            'inactive_clients' => $totalClients - $activeClients,
            'disqualified_clients' => (clone $clients)->whereNotNull('disqualified_at')->count(),
            'registration_completed' => (clone $clients)->whereNotNull('registration_completed_at')->count(),
            'clients_with_predictions' => (clone $clients)->whereIn('id', $predictedUserIds)->count(),
            'clients_without_predictions' => $totalClients - (clone $clients)->whereIn('id', $predictedUserIds)->count(),
            'clients_never_logged_in' => (clone $clients)->whereNull('last_login_at')->count(),
        ];
    }

    private function invoices(): array
    {
        $totalClients = User::where('role', 'client')->count();

        $perUser = RegisteredInvoice::query()
            ->selectRaw('user_id, COUNT(*) as c')
            ->groupBy('user_id')
            ->pluck('c', 'user_id');

        return [
            'total' => RegisteredInvoice::count(),
            'by_validation_status' => RegisteredInvoice::query()
                ->selectRaw('validation_status, COUNT(*) as c')
                ->groupBy('validation_status')
                ->pluck('c', 'validation_status'),
            'approved_amount' => (float) RegisteredInvoice::query()
                ->whereIn('validation_status', self::APPROVED_INVOICE_STATUSES)
                ->sum('purchase_amount'),
            'users_with_invoices' => $perUser->count(),
            'users_without_invoices' => $totalClients - $perUser->count(),
            'users_with_1_to_4' => $perUser->filter(fn ($c) => $c >= 1 && $c <= 4)->count(),
            'users_with_5_or_more' => $perUser->filter(fn ($c) => $c >= 5)->count(),
            'max_invoices_single_user' => $perUser->isEmpty() ? 0 : $perUser->max(),
        ];
    }

    private function predictionBreakdown(): array
    {
        $categoryExpression = "
            CASE
                WHEN home_score = away_score THEN 'draw'
                WHEN favorite_side = 'none' THEN 'sin_favorito'
                WHEN (home_score > away_score AND favorite_side = 'home')
                  OR (home_score < away_score AND favorite_side = 'away') THEN 'favorite'
                ELSE 'underdog'
            END
        ";

        $matchCounts = DB::table('tournament_matches')
            ->where('status', 'final')
            ->selectRaw("{$categoryExpression} as category, COUNT(*) as total")
            ->groupBy('category')
            ->pluck('total', 'category');

        $resultRows = DB::table('match_predictions as mp')
            ->join('tournament_matches as tm', 'tm.id', '=', 'mp.match_id')
            ->where('tm.status', 'final')
            ->selectRaw(str_replace(
                ['home_score', 'away_score', 'favorite_side'],
                ['tm.home_score', 'tm.away_score', 'tm.favorite_side'],
                $categoryExpression
            ) . ' as category, mp.result_type, COUNT(*) as total')
            ->groupBy('category', 'mp.result_type')
            ->get();

        $categories = [];
        foreach (['favorite', 'underdog', 'draw', 'sin_favorito'] as $key) {
            $categories[$key] = [
                'matches' => (int) ($matchCounts[$key] ?? 0),
                'exact' => 0,
                'outcome' => 0,
                'miss' => 0,
            ];
        }

        foreach ($resultRows as $row) {
            if (isset($categories[$row->category][$row->result_type])) {
                $categories[$row->category][$row->result_type] = (int) $row->total;
            }
        }

        $categories = array_filter($categories, fn ($cat) => $cat['matches'] > 0);

        return [
            'categories' => $categories,
            'finalized_matches' => TournamentMatch::where('status', 'final')->count(),
            'scheduled_matches' => TournamentMatch::where('status', 'scheduled')->count(),
            'graded_predictions' => array_sum(array_map(fn ($c) => $c['exact'] + $c['outcome'] + $c['miss'], $categories)),
            'pending_predictions' => MatchPrediction::where('result_type', 'pending')->count(),
            'total_predictions' => MatchPrediction::count(),
            'exact_total' => array_sum(array_column($categories, 'exact')),
            'outcome_total' => array_sum(array_column($categories, 'outcome')),
            'miss_total' => array_sum(array_column($categories, 'miss')),
        ];
    }

    private function points(TournamentPhase $phase): array
    {
        $totalClients = User::where('role', 'client')->count();

        $predictionPoints = MatchPrediction::query()
            ->where('phase_id', $phase->id)
            ->selectRaw('user_id, SUM(points_awarded) as pts')
            ->groupBy('user_id');

        $invoicePoints = RegisteredInvoice::query()
            ->whereIn('validation_status', self::APPROVED_INVOICE_STATUSES)
            ->whereBetween('issued_at', [$phase->starts_at, $phase->ends_at])
            ->selectRaw('user_id, SUM(points_awarded) as pts')
            ->groupBy('user_id');

        $clientsWithPoints = DB::table('users')
            ->leftJoinSub($predictionPoints, 'p', fn ($join) => $join->on('users.id', '=', 'p.user_id'))
            ->leftJoinSub($invoicePoints, 'i', fn ($join) => $join->on('users.id', '=', 'i.user_id'))
            ->where('users.role', 'client')
            ->selectRaw('COALESCE(p.pts, 0) + COALESCE(i.pts, 0) as total_points')
            ->pluck('total_points')
            ->filter(fn ($total) => $total > 0)
            ->count();

        return [
            'phase_name' => $phase->name,
            'prediction_points_total' => (int) MatchPrediction::where('phase_id', $phase->id)->sum('points_awarded'),
            'invoice_points_total' => (int) RegisteredInvoice::query()
                ->whereIn('validation_status', self::APPROVED_INVOICE_STATUSES)
                ->whereBetween('issued_at', [$phase->starts_at, $phase->ends_at])
                ->sum('points_awarded'),
            'clients_with_points' => $clientsWithPoints,
            'clients_without_points' => $totalClients - $clientsWithPoints,
        ];
    }

    private function fraud(): array
    {
        return [
            'total_flags' => FraudFlag::count(),
            'distinct_users_flagged' => FraudFlag::distinct('user_id')->count('user_id'),
            'by_type' => FraudFlag::query()
                ->selectRaw('flag_type, COUNT(*) as c')
                ->groupBy('flag_type')
                ->orderByDesc('c')
                ->pluck('c', 'flag_type'),
        ];
    }
}
