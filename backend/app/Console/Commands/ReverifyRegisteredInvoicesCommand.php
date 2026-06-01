<?php

namespace App\Console\Commands;

use App\Models\RegisteredInvoice;
use App\Support\ContestInvoiceVerifier;
use App\Support\FraudDetectionService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ReverifyRegisteredInvoicesCommand extends Command
{
    protected $signature = 'contest:reverify-invoices {--limit=500}';

    protected $description = 'Reverifica facturas aprobadas contra DGI para detectar anulaciones, notas de credito o devoluciones.';

    public function handle(ContestInvoiceVerifier $verifier, FraudDetectionService $fraudDetection): int
    {
        $limit = (int) $this->option('limit');
        $checked = 0;
        $flagged = 0;

        RegisteredInvoice::query()
            ->with('user')
            ->where('validation_status', 'approved')
            ->where(function ($query): void {
                $query->whereNull('last_reverified_at')
                    ->orWhere('last_reverified_at', '<=', now()->subDays(7));
            })
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (RegisteredInvoice $invoice) use ($verifier, $fraudDetection, &$checked, &$flagged): void {
                $checked++;

                try {
                    $resolved = $verifier->resolve($invoice->cufe);
                } catch (ValidationException $exception) {
                    $flagged++;
                    $fraudDetection->flag(
                        user: $invoice->user,
                        type: 'invoice_reverification_failed',
                        title: 'Factura aprobada no pudo reverificarse',
                        description: 'Durante el barrido semanal DGI no confirmo una factura previamente aprobada.',
                        severity: 'critical',
                        invoice: $invoice,
                        evidence: ['errors' => $exception->errors()],
                    );

                    return;
                }

                $payload = $resolved['payload'] ?? [];
                $status = strtolower((string) (data_get($payload, 'status') ?? data_get($payload, 'datos.status') ?? ''));
                $documentState = strtolower((string) (data_get($payload, 'estado') ?? data_get($payload, 'datos.estado') ?? ''));
                $hasCreditNote = (bool) (data_get($payload, 'has_credit_note') ?? data_get($payload, 'datos.has_credit_note') ?? false);

                if ($hasCreditNote || in_array($status, ['cancelled', 'canceled', 'void', 'annulled', 'anulada', 'devuelta'], true) || in_array($documentState, ['anulada', 'devuelta', 'nota_credito'], true)) {
                    $flagged++;
                    $fraudDetection->flag(
                        user: $invoice->user,
                        type: 'invoice_reversed_or_voided',
                        title: 'Factura aprobada con posible devolucion/anulacion',
                        description: 'El barrido semanal detecto indicios de nota de credito, anulacion o devolucion. No se removieron puntos automaticamente.',
                        severity: 'critical',
                        invoice: $invoice,
                        evidence: [
                            'status' => $status,
                            'document_state' => $documentState,
                            'has_credit_note' => $hasCreditNote,
                        ],
                    );
                }

                $invoice->forceFill([
                    'last_reverified_at' => now(),
                    'dgi_response_payload' => $payload,
                ])->save();
            });

        $this->info("Facturas reverificadas: {$checked}. Flags generados: {$flagged}.");

        return self::SUCCESS;
    }
}
