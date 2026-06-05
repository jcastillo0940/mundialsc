<?php

namespace App\Support;

use App\Models\DailyInvoiceGoal;
use App\Models\FraudFlag;
use App\Models\InvoiceGoalSetting;
use App\Models\RegisteredInvoice;
use App\Models\TournamentPhase;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContestInvoiceRegistrationService
{
    public function __construct(
        private readonly CampaignManager $campaignManager,
        private readonly CufeParser $cufeParser,
        private readonly ContestInvoiceVerifier $verifier,
        private readonly ContestRules $rules,
        private readonly WalletService $walletService,
        private readonly FraudDetectionService $fraudDetection,
        private readonly TournamentPhaseResolver $phaseResolver,
    ) {
    }

    public function register(User $user, array $data, ?Request $request = null): array
    {
        return $this->registerFor(
            targetUser: $user,
            data: $data,
            request: $request,
            actor: $user,
            registrationSource: 'client',
        );
    }

    public function registerAssisted(
        User $targetUser,
        array $data,
        User $actor,
        ?Request $request = null,
        ?FraudFlag $fraudFlag = null,
    ): array
    {
        return $this->registerFor(
            targetUser: $targetUser,
            data: $data,
            request: $request,
            actor: $actor,
            registrationSource: 'admin_assisted',
            assistanceNotes: $data['assistance_notes'] ?? null,
            fraudFlag: $fraudFlag,
        );
    }

    private function registerFor(
        User $targetUser,
        array $data,
        ?Request $request,
        User $actor,
        string $registrationSource,
        ?string $assistanceNotes = null,
        ?FraudFlag $fraudFlag = null,
    ): array
    {
        if ($targetUser->disqualified_at) {
            throw ValidationException::withMessages([
                'account' => 'Tu cuenta fue descalificada y no puede registrar facturas en el concurso.',
            ]);
        }

        $campaign = $this->campaignManager->activeOrFail();
        $settings = InvoiceGoalSetting::query()->first();

        if (! $campaign->invoice_scan_enabled || ($settings && ! $settings->is_enabled)) {
            throw ValidationException::withMessages([
                'invoice' => 'El registro de facturas esta deshabilitado en este momento.',
            ]);
        }

        $cufe = $this->cufeParser->extract($data['qr_raw_text']);

        if (! $cufe) {
            $this->fraudDetection->flag(
                user: $targetUser,
                type: 'invalid_cufe_format',
                title: 'Intento de registro sin CUFE valido',
                description: 'El contenido enviado no permitio extraer un CUFE valido.',
                severity: 'high',
                evidence: ['raw_text_sample' => substr((string) $data['qr_raw_text'], 0, 180)],
                request: $request,
            );

            throw ValidationException::withMessages([
                'qr_raw_text' => 'No fue posible extraer un CUFE valido del QR enviado.',
            ]);
        }

        try {
            $cacheKey = 'dgi_v2_cufe_' . strtolower($cufe);
            $cached = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($cufe) {
                $r = $this->verifier->resolve($cufe);
                return array_merge($r, ['issued_at' => $r['issued_at']->toIso8601String()]);
            });
            $issuedAtRaw = $cached['issued_at'];
            $resolvedInvoice = array_merge($cached, [
                'issued_at' => is_string($issuedAtRaw)
                    ? CarbonImmutable::parse($issuedAtRaw, 'America/Panama')
                    : CarbonImmutable::now('America/Panama'),
            ]);
        } catch (ConnectionException) {
            throw ValidationException::withMessages([
                'qr_raw_text' => 'No fue posible conectar con el servicio DGI. Intenta de nuevo en unos segundos.',
            ]);
        } catch (ValidationException $exception) {
            $this->fraudDetection->flag(
                user: $targetUser,
                type: 'dgi_invoice_resolution_failed',
                title: 'CUFE no confirmado por DGI',
                description: 'DGI rechazo o no confirmo el CUFE durante la resolucion inicial.',
                severity: 'critical',
                evidence: [
                    'cufe' => strtoupper($cufe),
                    'errors' => $exception->errors(),
                ],
                request: $request,
            );

            Audit::log('invoice.dgi_resolution_failed', 'registered_invoice', null, $actor, $request, [
                'cufe' => strtoupper($cufe),
                'errors' => $exception->errors(),
                'participant_user_id' => $targetUser->id,
                'registration_source' => $registrationSource,
            ]);

            throw $exception;
        }
        $canonicalCufe = strtoupper((string) $resolvedInvoice['cufe']);
        $issuedAt = $resolvedInvoice['issued_at'];
        $minimumAmount = $settings ? (float) $settings->min_purchase_amount : $this->rules->minimumInvoiceAmount();
        $purchaseAmount = round((float) $resolvedInvoice['purchase_amount'], 2);
        $now = now('America/Panama');
        $officialIssuerRucs = config('contest.official_issuer_rucs', []);
        $issuerRuc = strtoupper(trim((string) ($resolvedInvoice['issuer_ruc'] ?? '')));

        if ($officialIssuerRucs && ! in_array($issuerRuc, array_map(fn ($ruc) => strtoupper(trim((string) $ruc)), $officialIssuerRucs), true)) {
            $this->fraudDetection->flag(
                user: $targetUser,
                type: 'issuer_ruc_mismatch',
                title: 'Factura no emitida por RUC oficial',
                description: 'El RUC emisor devuelto por DGI no coincide con los RUC oficiales configurados para Super Carnes.',
                severity: 'critical',
                evidence: [
                    'cufe' => $canonicalCufe,
                    'issuer_ruc' => $issuerRuc,
                    'allowed_rucs' => $officialIssuerRucs,
                ],
                request: $request,
            );

            throw ValidationException::withMessages([
                'issuer_ruc' => 'La factura no corresponde a un emisor autorizado de Super Carnes.',
            ]);
        }

        if ($purchaseAmount <= $minimumAmount) {
            throw ValidationException::withMessages([
                'purchase_amount' => 'La factura debe ser mayor a $'.number_format($minimumAmount, 2).' para otorgar el punto adicional.',
            ]);
        }

        $maxInvoiceAgeDays = $settings ? (int) $settings->max_invoice_age_days : $this->rules->maxInvoiceAgeDays();
        $oldestAllowed = $now->copy()->startOfDay()->subDays($maxInvoiceAgeDays);

        if ($issuedAt->lt($oldestAllowed)) {
            $this->fraudDetection->flag(
                user: $targetUser,
                type: 'invoice_outside_allowed_age',
                title: 'Factura fuera de antiguedad permitida',
                description: 'La factura excede la antiguedad maxima permitida por los terminos del concurso.',
                severity: 'medium',
                evidence: [
                    'cufe' => $canonicalCufe,
                    'issued_at' => $issuedAt->toIso8601String(),
                    'max_invoice_age_days' => $maxInvoiceAgeDays,
                ],
                request: $request,
            );

            throw ValidationException::withMessages([
                'issued_at' => 'Factura expirada para la promocion.',
            ]);
        }

        if ($issuedAt->gt($now->copy()->endOfDay())) {
            throw ValidationException::withMessages([
                'issued_at' => 'La fecha de la factura no puede ser futura.',
            ]);
        }

        $verification = $this->verifier->verify($targetUser, [
            'cufe' => $canonicalCufe,
            'purchase_amount' => $purchaseAmount,
            'issued_at' => $issuedAt,
        ], $resolvedInvoice);
        $canonicalCufe = strtoupper((string) ($verification['canonical_cufe'] ?? $canonicalCufe));
        $invoicePhase = $this->phaseResolver->phaseForDate($issuedAt);

        try {
            $invoice = DB::transaction(function () use ($targetUser, $campaign, $data, $canonicalCufe, $purchaseAmount, $issuedAt, $verification, $resolvedInvoice, $minimumAmount, $maxInvoiceAgeDays, $settings, $request, $invoicePhase, $registrationSource, $actor, $assistanceNotes, $fraudFlag): RegisteredInvoice {
                $status = $verification['status'] === 'approved' ? 'accepted' : ($verification['status'] === 'pending' ? 'pending_validation' : 'rejected');
                $validationStatus = match ($verification['status']) {
                    'approved' => 'approved',
                    'pending' => 'pending',
                    default => 'rejected',
                };
                $pointsAwarded = $verification['status'] === 'approved' ? 1 : 0;

                $invoice = RegisteredInvoice::query()->create([
                    'user_id' => $targetUser->id,
                    'campaign_id' => $campaign->id,
                    'branch_id' => $data['branch_id'] ?? null,
                    'cufe' => $canonicalCufe,
                    'qr_raw_text' => $data['qr_raw_text'],
                    'invoice_number' => $resolvedInvoice['invoice_number'],
                    'issuer_ruc' => $resolvedInvoice['issuer_ruc'] ?? null,
                    'issuer_name' => $resolvedInvoice['issuer_name'] ?? null,
                    'issued_at' => $issuedAt,
                    'purchase_amount' => $purchaseAmount,
                    'points_awarded' => $pointsAwarded,
                    'shots_awarded' => 0,
                    'daily_points_capped' => false,
                    'daily_invoice_limit_hit' => false,
                    'status' => $status,
                    'validation_status' => $validationStatus,
                    'validation_notes' => $verification['notes'],
                    'dgi_checked_at' => $verification['status'] === 'pending' ? null : now(),
                    'dgi_response_payload' => $verification['payload'],
                    'registration_source' => $registrationSource,
                    'registered_by_user_id' => $registrationSource === 'admin_assisted' ? $actor->id : null,
                    'assisted_by_fraud_flag_id' => $fraudFlag?->id,
                    'assistance_notes' => $assistanceNotes,
                ]);

                if ($verification['status'] === 'approved') {
                    $this->syncDailyInvoiceGoal($targetUser, $invoice, $invoicePhase);
                    $this->walletService->creditGoals(
                        user: $targetUser,
                        amount: (int) $pointsAwarded,
                        type: 'invoice_goal_awarded',
                        resourceType: 'registered_invoice',
                        resourceId: $invoice->id,
                        campaignId: $campaign->id,
                        notes: 'Factura validada y punto acreditado.',
                        meta: [
                            'source' => 'invoice',
                            'registration_source' => $registrationSource,
                            'registered_by_user_id' => $registrationSource === 'admin_assisted' ? $actor->id : null,
                            'assisted_by_fraud_flag_id' => $fraudFlag?->id,
                            'phase_id' => $invoicePhase?->id,
                            'phase_slug' => $invoicePhase?->slug,
                            'phase_name' => $invoicePhase?->name,
                            'rule_code' => 'invoice_goal_awarded',
                            'rule_label' => 'Factura aprobada mayor al minimo',
                            'rule_snapshot' => [
                                'minimum_amount' => $minimumAmount,
                                'max_invoice_age_days' => $maxInvoiceAgeDays,
                                'goal_value' => (int) $pointsAwarded,
                                'validation_mode' => $settings?->validation_mode ?? 'api',
                            ],
                            'invoice' => [
                                'invoice_number' => $invoice->invoice_number,
                                'cufe' => $invoice->cufe,
                                'purchase_amount' => (float) $invoice->purchase_amount,
                                'validation_status' => $invoice->validation_status,
                            ],
                        ],
                    );
                    $this->fraudDetection->inspectApprovedInvoice($targetUser, $invoice, $request);
                }

                if ($verification['status'] === 'disqualify') {
                    $this->fraudDetection->flag(
                        user: $targetUser,
                        type: 'critical_invoice_validation',
                        title: 'Factura requiere revision antifraude',
                        description: (string) $verification['notes'].' El sistema no descalifico automaticamente al participante; requiere decision de auditor.',
                        severity: 'critical',
                        invoice: $invoice,
                        evidence: [
                            'cufe' => $canonicalCufe,
                            'validation_status' => $verification['status'],
                        ],
                        request: $request,
                    );
                }

                if ($fraudFlag) {
                    $resolutionPrefix = 'Factura asistida registrada';
                    $resolutionBody = $assistanceNotes ? $resolutionPrefix.': '.$assistanceNotes : $resolutionPrefix.'.';

                    $fraudFlag->update([
                        'registered_invoice_id' => $invoice->id,
                        'status' => 'resolved',
                        'resolution_notes' => trim($resolutionBody.' CUFE: '.$invoice->cufe.'.'),
                        'reviewed_by_user_id' => $actor->id,
                        'reviewed_at' => now(),
                    ]);
                }

                Audit::log('invoice.registered', 'registered_invoice', $invoice->id, $actor, $request, [
                    'participant_user_id' => $targetUser->id,
                    'cufe' => $invoice->cufe,
                    'validation_status' => $invoice->validation_status,
                    'points_awarded' => $invoice->points_awarded,
                    'registration_source' => $registrationSource,
                    'registered_by_user_id' => $registrationSource === 'admin_assisted' ? $actor->id : null,
                    'assisted_by_fraud_flag_id' => $fraudFlag?->id,
                    'assistance_notes' => $assistanceNotes,
                ]);

                return $invoice;
            });
        } catch (QueryException $exception) {
            if ((int) $exception->getCode() === 23000) {
                $this->fraudDetection->flag(
                    user: $targetUser,
                    type: 'duplicate_cufe_attempt',
                    title: 'Intento de registrar CUFE duplicado',
                    description: 'El CUFE ya habia sido registrado previamente por otro participante o por la misma cuenta.',
                    severity: 'high',
                    evidence: ['cufe' => $canonicalCufe],
                    request: $request,
                );

                Audit::log('invoice.duplicate_rejected', 'registered_invoice', null, $actor, $request, [
                    'cufe' => $canonicalCufe,
                    'participant_user_id' => $targetUser->id,
                    'registration_source' => $registrationSource,
                ]);

                throw ValidationException::withMessages([
                    'cufe' => 'Ese CUFE ya fue registrado previamente por otro participante.',
                ]);
            }

            throw $exception;
        }

        return [
            'invoice' => $invoice,
            'verification_status' => $verification['status'],
            'message' => $this->messageForStatus($verification['status']),
        ];
    }

    public function resolveInvoiceData(string $rawText): array
    {
        $cufe = $this->cufeParser->extract($rawText);

        if (! $cufe) {
            throw ValidationException::withMessages([
                'qr_raw_text' => 'No fue posible extraer un CUFE valido del contenido enviado.',
            ]);
        }

        try {
            $cacheKey = 'dgi_v2_cufe_' . strtolower($cufe);
            $cached = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($cufe) {
                $r = $this->verifier->resolve($cufe);
                return array_merge($r, ['issued_at' => $r['issued_at']->toIso8601String()]);
            });
            $issuedAtRaw = $cached['issued_at'];
            $resolved = array_merge($cached, [
                'issued_at' => is_string($issuedAtRaw)
                    ? CarbonImmutable::parse($issuedAtRaw, 'America/Panama')
                    : CarbonImmutable::now('America/Panama'),
            ]);
        } catch (ConnectionException) {
            throw ValidationException::withMessages([
                'qr_raw_text' => 'No fue posible conectar con el servicio DGI. Intenta de nuevo en unos segundos.',
            ]);
        }

        return [
            'cufe' => $resolved['cufe'],
            'invoice_number' => $resolved['invoice_number'],
            'purchase_amount' => number_format((float) $resolved['purchase_amount'], 2, '.', ''),
            'issued_at' => $resolved['issued_at']->toDateString(),
            'issuer_name' => $resolved['issuer_name'],
        ];
    }

    private function syncDailyInvoiceGoal(User $user, RegisteredInvoice $invoice, ?TournamentPhase $phase): void
    {
        DailyInvoiceGoal::query()->create([
            'user_id' => $user->id,
            'phase_id' => $phase?->id,
            'invoice_number' => $invoice->invoice_number ?? $invoice->cufe,
            'purchase_amount' => $invoice->purchase_amount,
            'invoice_date' => optional($invoice->issued_at)->toDateString() ?? now()->toDateString(),
            'goal_points_awarded' => 1,
            'validation_status' => 'approved',
            'validation_notes' => 'Factura aprobada para PRONOSTICA EL MUNDIAL Y GANA.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function messageForStatus(string $status): string
    {
        return match ($status) {
            'approved' => 'Factura validada y punto acreditado.',
            'pending' => 'Factura recibida. El punto se acreditara cuando la verificacion DGI sea confirmada.',
            'disqualify' => 'Factura enviada a revision antifraude. No se acreditaron puntos por esta factura.',
            default => 'Factura rechazada durante la validacion.',
        };
    }
}
