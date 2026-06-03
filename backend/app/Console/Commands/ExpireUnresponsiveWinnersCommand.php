<?php

namespace App\Console\Commands;

use App\Models\PromoWinner;
use App\Support\Audit;
use App\Support\PromotionRankingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireUnresponsiveWinnersCommand extends Command
{
    protected $signature = 'contest:expire-unresponsive-winners';

    protected $description = 'Descalifica ganadores sin respuesta despues de 5 dias y promueve al siguiente elegible.';

    public function handle(PromotionRankingService $ranking): int
    {
        $expired = 0;

        PromoWinner::query()
            ->whereIn('status', ['selected', 'contacting'])
            ->whereNotNull('last_contact_at')
            ->where('last_contact_at', '<=', now()->subDays(5))
            ->with('prizeToken')
            ->get()
            ->each(function (PromoWinner $winner) use ($ranking, &$expired): void {
                $next = DB::transaction(function () use ($winner, $ranking): ?array {
                    $token = $winner->prizeToken;

                    $winner->update([
                        'status' => 'disqualified',
                        'prize_token_id' => null,
                        'notes' => trim(($winner->notes ? $winner->notes."\n" : '').'Descalificado automaticamente por inactividad de 5 dias.'),
                        'disqualified_at' => now(),
                    ]);

                    $token?->update([
                        'status' => 'reassigned',
                        'current_promo_winner_id' => null,
                        'reassigned_from_promo_winner_id' => $winner->id,
                    ]);

                    $excludedUserIds = PromoWinner::query()
                        ->where('phase_id', $winner->phase_id)
                        ->pluck('user_id')
                        ->all();

                    $next = $ranking->nextEligibleCandidate($winner->phase_id, $excludedUserIds);

                    if ($next && $token) {
                        $replacement = PromoWinner::query()->updateOrCreate(
                            ['phase_id' => $winner->phase_id, 'user_id' => $next['user_id']],
                            [
                                'prize_token_id' => $token->id,
                                'leaderboard_position' => $next['position'],
                                'total_points' => $next['goals'],
                                'exact_hits' => $next['exact_hits'],
                                'invoice_count' => $next['invoice_count'],
                                'invoice_total_amount' => $next['invoice_total_amount'] ?? 0,
                                'goal_prediction_delta' => $next['goal_prediction_delta'] ?? null,
                                'ranking_timestamp' => $next['ranking_timestamp'] ?? null,
                                'selection_reason' => 'replacement',
                                'status' => 'selected',
                                'replacement_for_winner_id' => $winner->id,
                                'selected_at' => now(),
                            ],
                        );

                        $token->update([
                            'status' => 'awaiting_claim',
                            'current_promo_winner_id' => $replacement->id,
                            'assigned_user_id' => $replacement->user_id,
                            'assigned_at' => now(),
                        ]);
                    }

                    return $next;
                });

                Audit::log('promo.winner.expired_5d', 'promo_winner', $winner->id, null, null, [
                    'replacement_user_id' => $next['user_id'] ?? null,
                ]);

                $expired++;
            });

        $this->info("Ganadores expirados: {$expired}.");

        return self::SUCCESS;
    }
}
