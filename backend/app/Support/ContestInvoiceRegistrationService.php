<?php

namespace App\Support;

use App\Models\DailyInvoiceGoal;
use App\Models\InvoiceGoalSetting;
use App\Models\RegisteredInvoice;
use App\Models\TournamentPhase;
use App\Models\User;
use Illuminate\Database\QueryException;
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
    ) {
    }

    public function register(User $user, array $data): array
    {
        if ($user->disqualified_at) {
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
            throw ValidationException::withMessages([
                'qr_raw_text' => 'No fue posible extraer un CUFE valido del QR enviado.',
            ]);
        }

        $resolvedInvoice = $this->verifier->resolve($cufe);
        $canonicalCufe = strtoupper((string) $resolvedInvoice['cufe']);
        $issuedAt = $resolvedInvoice['issued_at'];
        $minimumAmount = $settings ? (float) $settings->min_purchase_amount : $this->rules->minimumInvoiceAmount();
        $purchaseAmount = round((float) $resolvedInvoice['purchase_amount'], 2);
        $now = now('America/Panama');

        if ($purchaseAmount <= $minimumAmount) {
            throw ValidationException::withMessages([
                'purchase_amount' => 'La factura debe ser mayor a $'.number_format($minimumAmount, 2).' para otorgar el punto adicional.',
            ]);
        }

        if ($issuedAt->month !== $now->month || $issuedAt->year !== $now->year) {
            throw ValidationException::withMessages([
                'issued_at' => 'La factura debe ser del mes en curso ('.$now->locale('es')->isoFormat('MMMM [de] YYYY').').',
            ]);
        }

        if ($issuedAt->gt($now->copy()->endOfDay())) {
            throw ValidationException::withMessages([
                'issued_at' => 'La fecha de la factura no puede ser futura.',
            ]);
        }

        $verification = $this->verifier->verify($user, [
            'cufe' => $canonicalCufe,
            'purchase_amount' => $purchaseAmount,
            'issued_at' => $issuedAt,
        ], $resolvedInvoice);
        $canonicalCufe = strtoupper((string) ($verification['canonical_cufe'] ?? $canonicalCufe));

        try {
            $invoice = DB::transaction(function () use ($user, $campaign, $data, $canonicalCufe, $purchaseAmount, $issuedAt, $verification, $resolvedInvoice): RegisteredInvoice {
                $status = $verification['status'] === 'approved' ? 'accepted' : ($verification['status'] === 'pending' ? 'pending_validation' : 'rejected');
                $validationStatus = match ($verification['status']) {
                    'approved' => 'approved',
                    'pending' => 'pending',
                    default => 'rejected',
                };
                $pointsAwarded = $verification['status'] === 'approved' ? 1 : 0;

                $invoice = RegisteredInvoice::query()->create([
                    'user_id' => $user->id,
                    'campaign_id' => $campaign->id,
                    'branch_id' => $data['branch_id'] ?? null,
                    'cufe' => $canonicalCufe,
                    'qr_raw_text' => $data['qr_raw_text'],
                    'invoice_number' => $resolvedInvoice['invoice_number'],
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
                ]);

                if ($verification['status'] === 'approved') {
                    $this->syncDailyInvoiceGoal($user, $invoice);
                    $this->walletService->creditGoals(
                        user: $user,
                        amount: (int) $pointsAwarded,
                        type: 'invoice_goal_awarded',
                        resourceType: 'registered_invoice',
                        resourceId: $invoice->id,
                        campaignId: $campaign->id,
                    );
                }

                if ($verification['status'] === 'disqualify') {
                    $this->disqualifyUser($user, (string) $verification['notes']);
                }

                return $invoice;
            });
        } catch (QueryException $exception) {
            if ((int) $exception->getCode() === 23000) {
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

        $resolved = $this->verifier->resolve($cufe);

        return [
            'cufe' => $resolved['cufe'],
            'invoice_number' => $resolved['invoice_number'],
            'purchase_amount' => number_format((float) $resolved['purchase_amount'], 2, '.', ''),
            'issued_at' => $resolved['issued_at']->toDateString(),
            'issuer_name' => $resolved['issuer_name'],
        ];
    }

    private function syncDailyInvoiceGoal(User $user, RegisteredInvoice $invoice): void
    {
        $phaseId = TournamentPhase::query()
            ->where('slug', 'fase-grupos')
            ->orderBy('stage_order')
            ->value('id');

        DailyInvoiceGoal::query()->create([
            'user_id' => $user->id,
            'phase_id' => $phaseId,
            'invoice_number' => $invoice->invoice_number ?? $invoice->cufe,
            'purchase_amount' => $invoice->purchase_amount,
            'invoice_date' => optional($invoice->issued_at)->toDateString() ?? now()->toDateString(),
            'goal_points_awarded' => 1,
            'validation_status' => 'approved',
            'validation_notes' => 'Factura aprobada para la polla mundialista.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function disqualifyUser(User $user, string $reason): void
    {
        $user->forceFill([
            'is_active' => false,
            'disqualified_at' => now(),
            'disqualification_reason' => $reason,
        ])->save();
    }

    private function messageForStatus(string $status): string
    {
        return match ($status) {
            'approved' => 'Factura validada y punto acreditado.',
            'pending' => 'Factura recibida. El punto se acreditara cuando la verificacion DGI sea confirmada.',
            'disqualify' => 'La factura genero una descalificacion del participante segun las reglas del concurso.',
            default => 'Factura rechazada durante la validacion.',
        };
    }
}
