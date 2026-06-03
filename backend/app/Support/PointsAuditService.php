<?php

namespace App\Support;

use App\Models\Coupon;
use App\Models\GamePlay;
use App\Models\MatchPrediction;
use App\Models\RegisteredInvoice;
use App\Models\TournamentPhase;
use App\Models\WalletMovement;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PointsAuditService
{
    public function __construct(
        private readonly TournamentPhaseResolver $phaseResolver,
    ) {
    }

    public function report(array $filters): array
    {
        $rows = $this->walletMovementRows($filters)
            ->concat($this->predictionRows($filters))
            ->sortByDesc(fn (array $row) => $row['occurred_at']?->timestamp ?? 0)
            ->values();

        return [
            'rows' => $rows,
            'summary' => [
                'entries' => $rows->count(),
                'users' => $rows->pluck('user_id')->unique()->count(),
                'goals_awarded' => $rows->sum(fn (array $row) => max((int) $row['goals_delta'], 0)),
                'goals_debited' => $rows->sum(fn (array $row) => abs(min((int) $row['goals_delta'], 0))),
                'shots_debited' => $rows->sum(fn (array $row) => abs(min((int) $row['shots_delta'], 0))),
            ],
        ];
    }

    private function walletMovementRows(array $filters): Collection
    {
        $query = WalletMovement::query()
            ->with('user')
            ->when(
                $filters['query'] ?? null,
                fn ($movementQuery, string $query) => $movementQuery->whereHas('user', function ($userQuery) use ($query): void {
                    $userQuery->where(function ($nestedQuery) use ($query): void {
                        $nestedQuery
                            ->where('name', 'like', "%{$query}%")
                            ->orWhere('email', 'like', "%{$query}%")
                            ->orWhere('cedula', 'like', "%{$query}%");
                    });
                }),
            )
            ->when(
                $filters['date_from'] ?? null,
                fn ($movementQuery, string $dateFrom) => $movementQuery->whereDate('created_at', '>=', $dateFrom),
            )
            ->when(
                $filters['date_to'] ?? null,
                fn ($movementQuery, string $dateTo) => $movementQuery->whereDate('created_at', '<=', $dateTo),
            )
            ->when(
                $filters['impact'] ?? 'all',
                function ($movementQuery, string $impact): void {
                    if ($impact === 'gain') {
                        $movementQuery->where('goals_delta', '>', 0);
                    } elseif ($impact === 'loss') {
                        $movementQuery->where(function ($nestedQuery): void {
                            $nestedQuery->where('goals_delta', '<', 0)->orWhere('shots_delta', '<', 0);
                        });
                    }
                },
            )
            ->latest('id')
            ->limit(400);

        $movements = $query->get();
        $movements = $this->filterWalletMovements($movements, $filters);

        $invoiceIds = $movements->where('resource_type', 'registered_invoice')->pluck('resource_id')->filter()->unique()->all();
        $couponIds = $movements->where('resource_type', 'coupon')->pluck('resource_id')->filter()->unique()->all();
        $playIds = $movements->where('resource_type', 'game_play')->pluck('resource_id')->filter()->unique()->all();

        $invoiceMap = RegisteredInvoice::query()->whereIn('id', $invoiceIds)->get()->keyBy('id');
        $couponMap = Coupon::query()->with('prize')->whereIn('id', $couponIds)->get()->keyBy('id');
        $playMap = GamePlay::query()->with('prize')->whereIn('id', $playIds)->get()->keyBy('id');
        $phasesById = TournamentPhase::query()->get()->keyBy('id');

        return $movements->map(function (WalletMovement $movement) use ($invoiceMap, $couponMap, $playMap, $phasesById): array {
            $source = $this->walletSource($movement);
            $invoice = $movement->resource_type === 'registered_invoice' ? $invoiceMap->get($movement->resource_id) : null;
            $coupon = $movement->resource_type === 'coupon' ? $couponMap->get($movement->resource_id) : null;
            $play = $movement->resource_type === 'game_play' ? $playMap->get($movement->resource_id) : null;
            $detail = $this->walletMovementDetail($movement, $invoice, $coupon, $play, $phasesById);

            return [
                'entry_key' => 'wallet-'.$movement->id,
                'source' => $source,
                'user_id' => $movement->user_id,
                'user_name' => $movement->user?->name ?? 'Usuario sin nombre',
                'user_email' => $movement->user?->email,
                'occurred_at' => $movement->created_at,
                'phase_name' => $detail['phase_name'],
                'goals_delta' => (int) $movement->goals_delta,
                'shots_delta' => (int) $movement->shots_delta,
                'rule_code' => $detail['rule_code'],
                'rule_label' => $detail['rule_label'],
                'reason' => $detail['reason'],
                'movement_type' => $movement->type,
                'reference' => $detail['reference'],
                'detail' => $detail['detail'],
            ];
        });
    }

    private function predictionRows(array $filters): Collection
    {
        if (($filters['source'] ?? 'all') === 'wallet') {
            return collect();
        }

        $query = MatchPrediction::query()
            ->with(['user', 'match.phase', 'match.homeTeam', 'match.awayTeam'])
            ->where('points_awarded', '>', 0)
            ->when(
                $filters['query'] ?? null,
                fn ($predictionQuery, string $query) => $predictionQuery->whereHas('user', function ($userQuery) use ($query): void {
                    $userQuery->where(function ($nestedQuery) use ($query): void {
                        $nestedQuery
                            ->where('name', 'like', "%{$query}%")
                            ->orWhere('email', 'like', "%{$query}%")
                            ->orWhere('cedula', 'like', "%{$query}%");
                    });
                }),
            )
            ->when(
                $filters['phase_id'] ?? null,
                fn ($predictionQuery, string $phaseId) => $predictionQuery->where('phase_id', (int) $phaseId),
            )
            ->when(
                $filters['date_from'] ?? null,
                fn ($predictionQuery, string $dateFrom) => $predictionQuery->whereDate('updated_at', '>=', $dateFrom),
            )
            ->when(
                $filters['date_to'] ?? null,
                fn ($predictionQuery, string $dateTo) => $predictionQuery->whereDate('updated_at', '<=', $dateTo),
            )
            ->latest('updated_at')
            ->limit(400);

        $predictions = $query->get();

        if (($filters['impact'] ?? 'all') === 'loss') {
            return collect();
        }

        if (($filters['rule_code'] ?? null) !== null && $filters['rule_code'] !== '') {
            $needle = Str::lower((string) $filters['rule_code']);
            $predictions = $predictions->filter(function (MatchPrediction $prediction) use ($needle): bool {
                $rule = $this->predictionRuleDetail($prediction);

                return Str::contains(Str::lower($rule['rule_code']), $needle)
                    || Str::contains(Str::lower($rule['rule_label']), $needle);
            })->values();
        }

        return $predictions->map(function (MatchPrediction $prediction): array {
            $rule = $this->predictionRuleDetail($prediction);
            $match = $prediction->match;
            $phaseName = $match?->phase?->name ?? 'Sin fase';

            return [
                'entry_key' => 'prediction-'.$prediction->id,
                'source' => 'prediction',
                'user_id' => $prediction->user_id,
                'user_name' => $prediction->user?->name ?? 'Usuario sin nombre',
                'user_email' => $prediction->user?->email,
                'occurred_at' => $prediction->updated_at ?? $prediction->created_at,
                'phase_name' => $phaseName,
                'goals_delta' => (int) $prediction->points_awarded,
                'shots_delta' => 0,
                'rule_code' => $rule['rule_code'],
                'rule_label' => $rule['rule_label'],
                'reason' => $rule['reason'],
                'movement_type' => 'prediction_points_awarded',
                'reference' => $match
                    ? sprintf(
                        'Partido #%s: %s vs %s',
                        $match->match_number ?? $match->id,
                        $match->homeTeam?->name ?? 'Local',
                        $match->awayTeam?->name ?? 'Visitante',
                    )
                    : 'Pronostico sin partido cargado',
                'detail' => [
                    'prediction' => [
                        'id' => $prediction->id,
                        'predicted_score' => sprintf('%d-%d', $prediction->predicted_home_score, $prediction->predicted_away_score),
                        'actual_score' => $match ? sprintf('%d-%d', $match->home_score, $match->away_score) : null,
                        'result_type' => $prediction->result_type,
                        'points_awarded' => (int) $prediction->points_awarded,
                    ],
                    'rule' => $rule,
                ],
            ];
        });
    }

    private function filterWalletMovements(EloquentCollection $movements, array $filters): EloquentCollection
    {
        $filtered = $movements->filter(function (WalletMovement $movement) use ($filters): bool {
            $source = $this->walletSource($movement);

            if (($filters['source'] ?? 'all') !== 'all' && ($filters['source'] ?? 'all') !== 'wallet' && $source !== $filters['source']) {
                return false;
            }

            if (($filters['phase_id'] ?? null) && ! $this->movementMatchesPhaseFilter($movement, (int) $filters['phase_id'])) {
                return false;
            }

            if (($filters['rule_code'] ?? null) !== null && $filters['rule_code'] !== '') {
                $ruleCode = Str::lower((string) data_get($movement->meta, 'rule_code', $movement->type));
                $ruleLabel = Str::lower((string) data_get($movement->meta, 'rule_label', $movement->notes));
                $needle = Str::lower((string) $filters['rule_code']);

                if (! Str::contains($ruleCode, $needle) && ! Str::contains($ruleLabel, $needle)) {
                    return false;
                }
            }

            return true;
        });

        return new EloquentCollection($filtered->values()->all());
    }

    private function movementMatchesPhaseFilter(WalletMovement $movement, int $phaseId): bool
    {
        $phaseIdFromMeta = (int) data_get($movement->meta, 'phase_id', 0);

        if ($phaseIdFromMeta > 0) {
            return $phaseIdFromMeta === $phaseId;
        }

        if ($movement->resource_type === 'registered_invoice' && $movement->resource_id) {
            $invoice = RegisteredInvoice::query()->find($movement->resource_id);
            $invoicePhase = $this->phaseResolver->phaseForDate($invoice?->issued_at);

            return $invoicePhase && (int) $invoicePhase->id === $phaseId;
        }

        return false;
    }

    private function walletSource(WalletMovement $movement): string
    {
        $source = data_get($movement->meta, 'source');

        if (is_string($source) && $source !== '') {
            return $source;
        }

        return match ($movement->resource_type) {
            'registered_invoice' => 'invoice',
            'coupon' => 'redemption',
            'game_play' => 'game',
            default => 'wallet',
        };
    }

    private function walletMovementDetail(
        WalletMovement $movement,
        ?RegisteredInvoice $invoice,
        ?Coupon $coupon,
        ?GamePlay $play,
        Collection $phasesById,
    ): array {
        $meta = is_array($movement->meta) ? $movement->meta : [];
        $ruleCode = (string) ($meta['rule_code'] ?? $movement->type);
        $ruleLabel = (string) ($meta['rule_label'] ?? $this->defaultWalletRuleLabel($movement));
        $reason = (string) ($movement->notes ?: $ruleLabel);
        $phase = $this->movementPhase($movement, $invoice, $phasesById);
        $phaseName = $phase?->name;
        $reference = $movement->resource_type && $movement->resource_id
            ? sprintf('%s #%s', $movement->resource_type, $movement->resource_id)
            : 'Movimiento interno';
        $detail = [
            'movement' => [
                'id' => $movement->id,
                'type' => $movement->type,
                'resource_type' => $movement->resource_type,
                'resource_id' => $movement->resource_id,
                'goals_delta' => (int) $movement->goals_delta,
                'shots_delta' => (int) $movement->shots_delta,
                'notes' => $movement->notes,
            ],
            'rule' => [
                'rule_code' => $ruleCode,
                'rule_label' => $ruleLabel,
                'snapshot' => $meta['rule_snapshot'] ?? null,
                'source_meta' => $meta,
            ],
        ];

        if ($invoice) {
            $reference = 'Factura '.($invoice->invoice_number ?: $invoice->cufe);
            $detail['invoice'] = [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'cufe' => $invoice->cufe,
                'purchase_amount' => (float) $invoice->purchase_amount,
                'points_awarded' => (int) $invoice->points_awarded,
                'validation_status' => $invoice->validation_status,
                'validation_notes' => $invoice->validation_notes,
            ];
        }

        if ($coupon) {
            $reference = 'Cupon '.$coupon->code;
            $detail['coupon'] = [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'status' => $coupon->status,
                'source_type' => $coupon->source_type,
                'prize' => $coupon->prize ? [
                    'id' => $coupon->prize->id,
                    'name' => $coupon->prize->name,
                    'category' => $coupon->prize->category,
                    'points_cost' => $coupon->prize->points_cost,
                ] : null,
            ];
        }

        if ($play) {
            $reference = 'Juego '.$play->id;
            $detail['game_play'] = [
                'id' => $play->id,
                'game_type' => $play->game_type,
                'result_type' => $play->result_type,
                'played_at' => $play->played_at,
                'prize' => $play->prize ? [
                    'id' => $play->prize->id,
                    'name' => $play->prize->name,
                ] : null,
            ];
        }

        return [
            'rule_code' => $ruleCode,
            'rule_label' => $ruleLabel,
            'reason' => $reason,
            'phase_name' => $phaseName,
            'reference' => $reference,
            'detail' => $detail,
        ];
    }

    private function defaultWalletRuleLabel(WalletMovement $movement): string
    {
        return match ($movement->type) {
            'invoice_goal_awarded' => 'Puntos por factura aprobada',
            'redeem_points_debit' => 'Debito por canje directo',
            'game_shot_debit' => 'Consumo de tiro en juego',
            default => Str::headline(str_replace('_', ' ', $movement->type)),
        };
    }

    private function movementPhase(WalletMovement $movement, ?RegisteredInvoice $invoice, Collection $phasesById): ?TournamentPhase
    {
        $phaseIdFromMeta = (int) data_get($movement->meta, 'phase_id', 0);

        if ($phaseIdFromMeta > 0) {
            return $phasesById->get($phaseIdFromMeta);
        }

        if ($invoice) {
            return $this->phaseResolver->phaseForDate($invoice->issued_at);
        }

        return null;
    }

    private function predictionRuleDetail(MatchPrediction $prediction): array
    {
        $match = $prediction->match;
        $phaseSlug = (string) ($match?->phase?->slug ?? '');
        $favoriteSide = (string) ($match?->favorite_side ?? 'none');
        $actualOutcome = $this->outcome((int) $match?->home_score, (int) $match?->away_score);

        if ($phaseSlug === 'fase-grupos') {
            if ($prediction->result_type === 'exact') {
                $basePoints = $actualOutcome === 'draw' ? 2 : ($actualOutcome === $favoriteSide ? 1 : 3);

                return [
                    'rule_code' => 'group_stage_exact_score',
                    'rule_label' => 'Pronostico exacto',
                    'reason' => sprintf('Acierto exacto en fase de grupos. Regla base %d + bono exacto 3.', $basePoints),
                ];
            }

            return [
                'rule_code' => match ($actualOutcome) {
                    'draw' => 'group_stage_draw_outcome',
                    default => $actualOutcome === $favoriteSide ? 'group_stage_favorite_outcome' : 'group_stage_underdog_outcome',
                },
                'rule_label' => match ($actualOutcome) {
                    'draw' => 'Pronostico de empate acertado',
                    default => $actualOutcome === $favoriteSide ? 'Pronostico acertado de favorito' : 'Pronostico acertado de no favorito',
                },
                'reason' => match ($actualOutcome) {
                    'draw' => 'Acierto de empate en fase de grupos: 2 puntos.',
                    default => $actualOutcome === $favoriteSide
                        ? 'Acierto del resultado final cuando gano el favorito: 1 punto.'
                        : 'Acierto del resultado final cuando gano el no favorito: 3 puntos.',
                },
            ];
        }

        if ($prediction->result_type === 'exact') {
            return [
                'rule_code' => 'knockout_exact_score',
                'rule_label' => 'Pronostico exacto',
                'reason' => 'Acierto exacto fuera de fase de grupos.',
            ];
        }

        return [
            'rule_code' => 'knockout_outcome',
            'rule_label' => 'Pronostico de resultado acertado',
            'reason' => 'Acierto del resultado final fuera de fase de grupos.',
        ];
    }

    private function outcome(int $home, int $away): string
    {
        if ($home === $away) {
            return 'draw';
        }

        return $home > $away ? 'home' : 'away';
    }
}
