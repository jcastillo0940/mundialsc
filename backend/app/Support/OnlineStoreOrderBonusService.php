<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\OnlineStoreOrder;
use App\Models\OnlineStoreOrderClaim;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OnlineStoreOrderBonusService
{
    private const BONUS_POINTS = 5;
    private const MINIMUM_TOTAL = 25.00;
    private const DEFAULT_PROMO_START_AT = '2026-07-03 00:00:00';
    private const DEFAULT_PROMO_END_AT = '2026-07-06 23:59:59';
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

        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'order_number' => 'No encontramos esa orden en supercarnes.com. Verifica el numero exacto de orden Magento.',
            ]);
        }

        $order = $this->storeOrderSnapshot($payload, $user);

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
            'source' => 'client_frontend',
            'points_awarded' => 0,
            'submitted_at' => $claim->submitted_at ?? now(self::PROMO_TIMEZONE),
            'source_reported_at' => $claim->source_reported_at ?? now(self::PROMO_TIMEZONE),
            'reviewed_at' => null,
            'reviewed_by_user_id' => null,
            'created_by_user_id' => null,
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
                'claim' => 'La orden no cumple las reglas del bono: $25.00 o mas, fecha valida del 3 al 6 de julio y solicitud dentro de la promocion.',
            ]);
        }

        $credited = $this->creditOrder($order, $claim->user, $claim->source ?: 'client_frontend', $admin);

        $claim->forceFill([
            'online_store_order_id' => $order->id,
            'magento_order_id' => $order->magento_order_id,
            'customer_email' => $order->customer_email,
            'grand_total' => $order->grand_total,
            'currency' => $order->currency,
            'magento_status' => $order->status,
            'ordered_at' => $order->ordered_at,
            'status' => 'approved',
            'source' => $claim->source ?: 'client_frontend',
            'points_awarded' => $credited,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $admin->id,
            'review_notes' => $notes,
            'raw_payload' => $this->sanitizedPayload($payload),
        ])->save();

        return $claim->fresh();
    }

    public function approveManualWhatsappClaim(
        User $targetUser,
        ?string $orderNumber,
        CarbonImmutable $reportedAt,
        User $admin,
        ?string $notes = null,
    ): OnlineStoreOrderClaim {
        if (! filter_var(config('services.magento.order_bonus_enabled', false), FILTER_VALIDATE_BOOL)) {
            throw ValidationException::withMessages([
                'magento' => 'La verificacion de compras en linea no esta disponible todavia.',
            ]);
        }

        if ($targetUser->role !== 'client') {
            throw ValidationException::withMessages([
                'cedula' => 'El documento encontrado no pertenece a un cliente.',
            ]);
        }

        if ($targetUser->disqualified_at !== null) {
            throw ValidationException::withMessages([
                'cedula' => 'Este cliente esta descalificado y no puede recibir puntos.',
            ]);
        }

        $orderNumber = trim((string) $orderNumber);
        if ($orderNumber === '') {
            throw ValidationException::withMessages([
                'order_number' => 'Escribe el numero exacto de orden Magento.',
            ]);
        }

        $notes = trim((string) $notes);
        if ($notes === '') {
            throw ValidationException::withMessages([
                'review_notes' => 'Escribe una nota de auditoria para esta acreditacion manual.',
            ]);
        }

        if (! $this->dateIsInsidePromoWindow($reportedAt)) {
            throw ValidationException::withMessages([
                'source_reported_at' => 'El reporte por WhatsApp debe estar dentro de la promocion del 3 al 6 de julio.',
            ]);
        }

        $orders = $this->magento->ordersForIncrementId($orderNumber);
        $payload = $orders[0] ?? null;

        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'order_number' => 'No encontramos esa orden en supercarnes.com. Verifica el numero exacto de orden Magento.',
            ]);
        }

        $order = $this->storeOrderSnapshot($payload, $targetUser);

        if (! $this->isEligibleForManualWhatsappApproval($order, $targetUser, $reportedAt)) {
            throw ValidationException::withMessages([
                'claim' => 'La orden no cumple las reglas del bono: $25.00 o mas, fecha valida del 3 al 6 de julio, reporte dentro de promocion y sin credito previo.',
            ]);
        }

        return DB::transaction(function () use ($orderNumber, $targetUser, $order, $admin, $reportedAt, $notes, $payload): OnlineStoreOrderClaim {
            $claim = OnlineStoreOrderClaim::query()
                ->where('increment_id', $orderNumber)
                ->where('user_id', $targetUser->id)
                ->lockForUpdate()
                ->first();

            $claim ??= new OnlineStoreOrderClaim([
                'increment_id' => $orderNumber,
                'user_id' => $targetUser->id,
            ]);

            if ($claim->exists && $claim->status === 'approved') {
                throw ValidationException::withMessages([
                    'claim' => 'Esta orden ya fue aprobada para este cliente.',
                ]);
            }

            $credited = $this->creditOrder($order, $targetUser, 'admin_whatsapp', $admin);
            if ($credited <= 0) {
                throw ValidationException::withMessages([
                    'claim' => 'Esta orden ya tenia puntos acreditados.',
                ]);
            }

            $claim->forceFill([
                'user_id' => $targetUser->id,
                'online_store_order_id' => $order->id,
                'submitted_email' => strtolower((string) $targetUser->email),
                'magento_order_id' => $order->magento_order_id,
                'customer_email' => $order->customer_email,
                'grand_total' => $order->grand_total,
                'currency' => $order->currency,
                'magento_status' => $order->status,
                'ordered_at' => $order->ordered_at,
                'status' => 'approved',
                'source' => 'admin_whatsapp',
                'points_awarded' => $credited,
                'submitted_at' => $reportedAt,
                'source_reported_at' => $reportedAt,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $admin->id,
                'created_by_user_id' => $admin->id,
                'review_notes' => $notes,
                'raw_payload' => $this->sanitizedPayload($payload),
            ])->save();

            return $claim->fresh();
        });
    }

    public function approveManualOnlineOrderWithoutMagento(
        User $targetUser,
        string $reference,
        int $points,
        User $admin,
        string $notes,
    ): OnlineStoreOrderClaim {
        if ($targetUser->role !== 'client') {
            throw ValidationException::withMessages([
                'manual_cedula' => 'El documento encontrado no pertenece a un cliente.',
            ]);
        }

        if ($targetUser->disqualified_at !== null) {
            throw ValidationException::withMessages([
                'manual_cedula' => 'Este cliente esta descalificado y no puede recibir puntos.',
            ]);
        }

        $reference = trim($reference);
        if ($reference === '') {
            throw ValidationException::withMessages([
                'manual_order_reference' => 'Escribe el numero de orden o referencia.',
            ]);
        }

        $notes = trim($notes);
        if ($notes === '') {
            throw ValidationException::withMessages([
                'manual_review_notes' => 'Escribe una nota de auditoria para esta acreditacion manual.',
            ]);
        }

        if ($points <= 0 || $points > 50) {
            throw ValidationException::withMessages([
                'manual_points' => 'Los puntos deben estar entre 1 y 50.',
            ]);
        }

        return DB::transaction(function () use ($targetUser, $reference, $points, $admin, $notes): OnlineStoreOrderClaim {
            $existingApprovedClaim = OnlineStoreOrderClaim::query()
                ->where('increment_id', $reference)
                ->where('status', 'approved')
                ->lockForUpdate()
                ->first();

            if ($existingApprovedClaim) {
                throw ValidationException::withMessages([
                    'manual_order_reference' => 'Esta referencia ya fue aprobada anteriormente.',
                ]);
            }

            $order = OnlineStoreOrder::query()
                ->where('increment_id', $reference)
                ->lockForUpdate()
                ->first();

            if ($order && ($order->credited_at !== null || (int) $order->points_awarded > 0)) {
                throw ValidationException::withMessages([
                    'manual_order_reference' => 'Esta orden ya tiene puntos acreditados.',
                ]);
            }

            $order ??= new OnlineStoreOrder([
                'increment_id' => $reference,
            ]);

            $order->forceFill([
                'user_id' => $targetUser->id,
                'magento_order_id' => $order->magento_order_id,
                'customer_email' => strtolower((string) $targetUser->email),
                'grand_total' => 0,
                'currency' => 'USD',
                'status' => 'manual_approved',
                'ordered_at' => now(self::PROMO_TIMEZONE),
                'points_awarded' => $points,
                'credited_at' => now(),
                'raw_payload' => [
                    'source' => 'admin_manual_online_order',
                    'reference' => $reference,
                    'admin_user_id' => $admin->id,
                    'notes' => $notes,
                ],
            ])->save();

            $claim = OnlineStoreOrderClaim::query()
                ->where('increment_id', $reference)
                ->where('user_id', $targetUser->id)
                ->lockForUpdate()
                ->first();

            $claim ??= new OnlineStoreOrderClaim([
                'increment_id' => $reference,
                'user_id' => $targetUser->id,
            ]);

            $claim->forceFill([
                'user_id' => $targetUser->id,
                'online_store_order_id' => $order->id,
                'submitted_email' => strtolower((string) $targetUser->email),
                'magento_order_id' => null,
                'customer_email' => strtolower((string) $targetUser->email),
                'grand_total' => 0,
                'currency' => 'USD',
                'magento_status' => 'manual_approved',
                'ordered_at' => $order->ordered_at,
                'status' => 'approved',
                'source' => 'admin_manual_online_order',
                'points_awarded' => $points,
                'submitted_at' => now(),
                'source_reported_at' => now(),
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $admin->id,
                'created_by_user_id' => $admin->id,
                'review_notes' => $notes,
                'raw_payload' => [
                    'source' => 'admin_manual_online_order',
                    'reference' => $reference,
                    'admin_user_id' => $admin->id,
                    'points' => $points,
                    'notes' => $notes,
                ],
            ])->save();

            $this->walletService->creditGoals(
                user: $targetUser,
                amount: $points,
                type: 'online_store_purchase_bonus',
                resourceType: 'manual_online_store_order',
                resourceId: $order->id,
                campaignId: $this->activeCampaignId(),
                notes: 'Bono manual por compra en tienda en linea Super Carnes.',
                meta: [
                    'source' => 'admin_manual_online_order',
                    'rule_code' => 'manual_online_order_without_magento',
                    'admin_user_id' => $admin->id,
                    'user_id' => $targetUser->id,
                    'reference' => $reference,
                    'points' => $points,
                ],
            );

            return $claim->fresh();
        });
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
        return $this->dateIsInsidePromoWindow(now(self::PROMO_TIMEZONE))
            && strtolower($order->customer_email) === strtolower((string) $user->email)
            && (float) $order->grand_total >= self::MINIMUM_TOTAL
            && $order->ordered_at !== null
            && $this->dateIsInsidePromoWindow(CarbonImmutable::parse($order->ordered_at))
            && $order->credited_at === null
            && (int) $order->points_awarded === 0;
    }

    private function isEligibleForManualApproval(OnlineStoreOrder $order, OnlineStoreOrderClaim $claim): bool
    {
        return $claim->submitted_at !== null
            && $this->dateIsInsidePromoWindow(CarbonImmutable::parse($claim->submitted_at))
            && (float) $order->grand_total >= self::MINIMUM_TOTAL
            && $order->ordered_at !== null
            && $this->dateIsInsidePromoWindow(CarbonImmutable::parse($order->ordered_at))
            && $order->credited_at === null
            && (int) $order->points_awarded === 0
            && ($order->user_id === null || (int) $order->user_id === (int) $claim->user_id);
    }

    private function isEligibleForManualWhatsappApproval(OnlineStoreOrder $order, User $user, CarbonImmutable $reportedAt): bool
    {
        return $this->dateIsInsidePromoWindow($reportedAt)
            && (float) $order->grand_total >= self::MINIMUM_TOTAL
            && $order->ordered_at !== null
            && $this->dateIsInsidePromoWindow(CarbonImmutable::parse($order->ordered_at))
            && $order->credited_at === null
            && (int) $order->points_awarded === 0
            && ($order->user_id === null || (int) $order->user_id === (int) $user->id);
    }

    private function creditOrder(OnlineStoreOrder $order, User $user, string $source = 'client_frontend', ?User $admin = null): int
    {
        return DB::transaction(function () use ($order, $user, $source, $admin): int {
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

            $this->walletService->creditGoals(
                user: $user,
                amount: self::BONUS_POINTS,
                type: 'online_store_purchase_bonus',
                resourceType: 'online_store_order',
                resourceId: $lockedOrder->id,
                campaignId: $this->activeCampaignId(),
                notes: 'Bono por compra en tienda en linea Super Carnes.',
                meta: [
                    'source' => $source,
                    'rule_code' => 'online_store_order_july_4_to_6',
                    'admin_user_id' => $admin?->id,
                    'user_id' => $user->id,
                    'minimum_total' => self::MINIMUM_TOTAL,
                    'promo_start_at' => $this->promoStartAt()->toIso8601String(),
                    'promo_end_at' => $this->promoEndAt()->toIso8601String(),
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

    private function promoStartAt(): CarbonImmutable
    {
        return CarbonImmutable::parse(
            (string) config('services.magento.order_bonus_promo_start_at', self::DEFAULT_PROMO_START_AT),
            self::PROMO_TIMEZONE,
        );
    }

    private function activeCampaignId(): ?int
    {
        $campaignId = Campaign::query()
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->latest('id')
            ->value('id');

        return $campaignId ? (int) $campaignId : null;
    }

    private function promoEndAt(): CarbonImmutable
    {
        return CarbonImmutable::parse(
            (string) config('services.magento.order_bonus_promo_end_at', self::DEFAULT_PROMO_END_AT),
            self::PROMO_TIMEZONE,
        );
    }

    private function dateIsInsidePromoWindow(CarbonInterface $date): bool
    {
        $date = $date->setTimezone(self::PROMO_TIMEZONE);

        return $date->gte($this->promoStartAt()) && $date->lte($this->promoEndAt());
    }

    private function ensureClaimWindowIsOpen(): void
    {
        $now = now(self::PROMO_TIMEZONE);

        if (! $this->dateIsInsidePromoWindow(CarbonImmutable::parse($now))) {
            throw ValidationException::withMessages([
                'order_number' => 'El periodo para reportar compras en linea es del 3 al 6 de julio de 2026.',
            ]);
        }
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

        return 'No encontramos compras en linea validas de $25.00 o mas con el correo de tu cuenta.';
    }
}
