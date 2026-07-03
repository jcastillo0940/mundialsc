<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\OnlineStoreOrder;
use App\Models\OnlineStoreOrderClaim;
use App\Models\TournamentMatch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OnlineStoreOrderBonusService
{
    private const BONUS_POINTS = 5;
    private const MINIMUM_TOTAL = 20.00;
    private const PROMO_START_AT = '2026-06-02 00:00:00';
    private const PROMO_TIMEZONE = 'America/Panama';

    public function __construct(
        private readonly MagentoOrderApiClient $magento,
        private readonly WalletService $walletService,
    ) {
    }

    /**
     * @return array{credited_points:int, credited_orders:int, checked_orders:int, message:string}
     */
    public function verifyForUser(User $user, ?string $orderNumber = null): array
    {
        return $this->submitClaim($user, $orderNumber);
    }

    /**
     * @return array{status:string, credited_points:int, credited_orders:int, checked_orders:int, message:string}
     */
    public function submitClaim(User $user, ?string $orderNumber): array
    {
        if (! filter_var(config('services.magento.order_bonus_enabled', false), FILTER_VALIDATE_BOOL)) {
            throw ValidationException::withMessages([
                'magento' => 'La verificacion de compras en linea no esta disponible todavia.',
            ]);
        }

        $orderNumber = trim((string) $orderNumber);
        if ($orderNumber === '') {
            throw ValidationException::withMessages([
                'order_number' => 'Escribe el numero exacto de orden Magento.',
            ]);
        }

        $this->ensureClaimWindowIsOpen();

        $orders = $this->magento->ordersForIncrementId($orderNumber);
        $payload = $orders[0] ?? null;
        $order = is_array($payload) ? $this->storeOrderSnapshot($payload, $user) : null;

        $claim = OnlineStoreOrderClaim::query()
            ->where('increment_id', $orderNumber)
            ->where('user_id', $user->id)
            ->firstOrNew([
                'increment_id' => $orderNumber,
                'user_id' => $user->id,
            ]);

        if ($claim->exists && $claim->status === 'approved') {
            return [
                'status' => 'approved',
                'credited_points' => 0,
                'credited_orders' => 0,
                'checked_orders' => count($orders),
                'message' => 'Esta orden ya fue aprobada y sus puntos fueron acreditados.',
            ];
        }

        $claim->fill([
            'user_id' => $user->id,
            'online_store_order_id' => $order?->id,
            'submitted_email' => strtolower((string) $user->email),
            'magento_order_id' => $order?->magento_order_id,
            'customer_email' => $order?->customer_email,
            'grand_total' => $order?->grand_total ?? 0,
            'currency' => $order?->currency ?? 'USD',
            'magento_status' => $order?->status,
            'ordered_at' => $order?->ordered_at,
            'status' => 'pending',
            'points_awarded' => 0,
            'submitted_at' => $claim->submitted_at ?? now(self::PROMO_TIMEZONE),
            'reviewed_at' => null,
            'reviewed_by_user_id' => null,
            'review_notes' => null,
            'raw_payload' => $this->sanitizedPayload($payload),
        ])->save();

        return [
            'status' => 'pending',
            'credited_points' => 0,
            'credited_orders' => 0,
            'checked_orders' => count($orders),
            'message' => 'Recibimos tu solicitud de compra en linea. Un administrador revisara tu orden antes de acreditar los puntos.',
        ];
    }

    public function approveClaim(OnlineStoreOrderClaim $claim, User $admin, ?string $notes = null): OnlineStoreOrderClaim
    {
        if ($claim->status !== 'pending') {
            throw ValidationException::withMessages([
                'claim' => 'Esta solicitud ya fue revisada.',
            ]);
        }

        $orders = $this->magento->ordersForIncrementId($claim->increment_id);
        $payload = $orders[0] ?? $claim->raw_payload;
        $order = is_array($payload) ? $this->storeOrderSnapshot($payload, $claim->user) : $claim->order;

        if (! $order || ! $this->isEligibleForManualApproval($order, $claim)) {
            throw ValidationException::withMessages([
                'claim' => 'La orden no cumple las reglas del bono: $20.00 o mas, fecha valida, estado valido y solicitud antes de octavos.',
            ]);
        }

        $credited = $this->creditOrder($order, $claim->user);

        $claim->forceFill([
            'online_store_order_id' => $order->id,
            'magento_order_id' => $order->magento_order_id,
            'customer_email' => $order->customer_email,
            'grand_total' => $order->grand_total,
            'currency' => $order->currency,
            'magento_status' => $order->status,
            'ordered_at' => $order->ordered_at,
            'status' => 'approved',
            'points_awarded' => $credited,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $admin->id,
            'review_notes' => $notes,
            'raw_payload' => $this->sanitizedPayload($payload),
        ])->save();

        return $claim->fresh();
    }

    public function rejectClaim(OnlineStoreOrderClaim $claim, User $admin, ?string $notes = null): OnlineStoreOrderClaim
    {
        if ($claim->status !== 'pending') {
            throw ValidationException::withMessages([
                'claim' => 'Esta solicitud ya fue revisada.',
            ]);
        }

        $claim->forceFill([
            'status' => 'rejected',
            'points_awarded' => 0,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $admin->id,
            'review_notes' => $notes,
        ])->save();

        return $claim->fresh();
    }

    private function storeOrderSnapshot(array $payload, User $user): OnlineStoreOrder
    {
        $magentoOrderId = (string) ($payload['entity_id'] ?? '');
        $incrementId = (string) ($payload['increment_id'] ?? '');
        $email = strtolower(trim((string) ($payload['customer_email'] ?? '')));
        $orderedAt = $this->parseDate($payload['created_at'] ?? null);

        $lookup = $magentoOrderId !== ''
            ? ['magento_order_id' => $magentoOrderId]
            : ['increment_id' => $incrementId];

        $order = OnlineStoreOrder::query()->firstOrNew($lookup);
        $order->fill([
            'magento_order_id' => $magentoOrderId !== '' ? $magentoOrderId : null,
            'increment_id' => $incrementId !== '' ? $incrementId : null,
            'customer_email' => $email,
            'grand_total' => round((float) ($payload['grand_total'] ?? 0), 2),
            'currency' => (string) ($payload['order_currency_code'] ?? $payload['base_currency_code'] ?? 'USD'),
            'status' => strtolower((string) ($payload['status'] ?? '')),
            'ordered_at' => $orderedAt,
            'raw_payload' => $this->sanitizedPayload($payload),
        ]);

        if ($email === strtolower((string) $user->email) && ($order->user_id === null || (int) $order->user_id === (int) $user->id)) {
            $order->user_id = $user->id;
        }

        $order->save();

        return $order;
    }

    private function isEligibleForBonus(OnlineStoreOrder $order, User $user): bool
    {
        $cutoff = $this->firstRoundOf16Kickoff();
        $promoStart = CarbonImmutable::parse(self::PROMO_START_AT, self::PROMO_TIMEZONE);

        return $cutoff !== null
            && now(self::PROMO_TIMEZONE)->lt($cutoff->copy()->setTimezone(self::PROMO_TIMEZONE))
            && strtolower($order->customer_email) === strtolower((string) $user->email)
            && (float) $order->grand_total >= self::MINIMUM_TOTAL
            && in_array(strtolower($order->status), $this->eligibleStatuses(), true)
            && $order->ordered_at !== null
            && $order->ordered_at->gte($promoStart)
            && $order->ordered_at->lt($cutoff)
            && $order->credited_at === null
            && (int) $order->points_awarded === 0
            && ($order->user_id === null || (int) $order->user_id === (int) $user->id);
    }

    private function isEligibleForManualApproval(OnlineStoreOrder $order, OnlineStoreOrderClaim $claim): bool
    {
        $cutoff = $this->firstRoundOf16Kickoff();
        $promoStart = CarbonImmutable::parse(self::PROMO_START_AT, self::PROMO_TIMEZONE);

        return $cutoff !== null
            && $claim->submitted_at !== null
            && $claim->submitted_at->lt($cutoff)
            && (float) $order->grand_total >= self::MINIMUM_TOTAL
            && in_array(strtolower($order->status), $this->eligibleStatuses(), true)
            && $order->ordered_at !== null
            && $order->ordered_at->gte($promoStart)
            && $order->ordered_at->lt($cutoff)
            && $order->credited_at === null
            && (int) $order->points_awarded === 0
            && ($order->user_id === null || (int) $order->user_id === (int) $claim->user_id);
    }

    private function creditOrder(OnlineStoreOrder $order, User $user): int
    {
        return DB::transaction(function () use ($order, $user): int {
            $lockedOrder = OnlineStoreOrder::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->credited_at !== null || (int) $lockedOrder->points_awarded > 0) {
                return 0;
            }

            $lockedOrder->forceFill([
                'user_id' => $user->id,
                'points_awarded' => self::BONUS_POINTS,
                'credited_at' => now(),
            ])->save();

            $campaignId = Campaign::query()
                ->where('status', 'active')
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->latest('id')
                ->value('id');

            $this->walletService->creditGoals(
                user: $user,
                amount: self::BONUS_POINTS,
                type: 'online_store_purchase_bonus',
                resourceType: 'online_store_order',
                resourceId: $lockedOrder->id,
                campaignId: $campaignId ? (int) $campaignId : null,
                notes: 'Bono por compra en tienda en linea Super Carnes.',
                meta: [
                    'source' => 'magento',
                    'rule_code' => 'online_store_order_before_round_of_16',
                    'minimum_total' => self::MINIMUM_TOTAL,
                    'promo_start_at' => CarbonImmutable::parse(self::PROMO_START_AT, self::PROMO_TIMEZONE)->toIso8601String(),
                    'promo_end_at' => $this->firstRoundOf16Kickoff()?->toIso8601String(),
                    'order' => [
                        'magento_order_id' => $lockedOrder->magento_order_id,
                        'increment_id' => $lockedOrder->increment_id,
                        'grand_total' => (float) $lockedOrder->grand_total,
                        'status' => $lockedOrder->status,
                        'ordered_at' => optional($lockedOrder->ordered_at)->toIso8601String(),
                    ],
                ],
            );

            return self::BONUS_POINTS;
        });
    }

    private function firstRoundOf16Kickoff(): ?CarbonImmutable
    {
        $kickoff = TournamentMatch::query()
            ->join('tournament_phases', 'tournament_phases.id', '=', 'tournament_matches.phase_id')
            ->where('tournament_phases.slug', 'octavos')
            ->min('tournament_matches.kickoff_at');

        return $kickoff ? CarbonImmutable::parse($kickoff) : null;
    }

    private function ensureClaimWindowIsOpen(): void
    {
        $cutoff = $this->firstRoundOf16Kickoff();

        if ($cutoff === null || now(self::PROMO_TIMEZONE)->gte($cutoff->copy()->setTimezone(self::PROMO_TIMEZONE))) {
            throw ValidationException::withMessages([
                'order_number' => 'El periodo para reportar compras en linea ya finalizo.',
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function eligibleStatuses(): array
    {
        $statuses = config('services.magento.order_bonus_statuses', ['processing', 'complete']);

        if (is_string($statuses)) {
            $statuses = explode(',', $statuses);
        }

        return array_values(array_filter(array_map(
            fn ($status) => strtolower(trim((string) $status)),
            is_array($statuses) ? $statuses : [],
        )));
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! $value) {
            return null;
        }

        return CarbonImmutable::parse((string) $value);
    }

    private function sanitizedPayload(?array $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        return [
            'entity_id' => $payload['entity_id'] ?? null,
            'increment_id' => $payload['increment_id'] ?? null,
            'customer_email' => $payload['customer_email'] ?? null,
            'grand_total' => $payload['grand_total'] ?? null,
            'order_currency_code' => $payload['order_currency_code'] ?? null,
            'base_currency_code' => $payload['base_currency_code'] ?? null,
            'status' => $payload['status'] ?? null,
            'created_at' => $payload['created_at'] ?? null,
            'store_id' => $payload['store_id'] ?? null,
            'store_name' => $payload['store_name'] ?? null,
        ];
    }

    private function messageFor(int $creditedPoints, int $checkedOrders): string
    {
        if ($creditedPoints > 0) {
            return "Encontramos tu compra en linea y acreditamos {$creditedPoints} puntos.";
        }

        if ($checkedOrders > 0) {
            return 'Tus compras en linea ya fueron revisadas. No hay puntos nuevos por acreditar.';
        }

        return 'No encontramos compras en linea validas de $20.00 o mas con el correo de tu cuenta.';
    }
}
