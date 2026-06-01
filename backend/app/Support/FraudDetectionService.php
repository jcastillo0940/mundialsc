<?php

namespace App\Support;

use App\Models\FraudFlag;
use App\Models\RegisteredInvoice;
use App\Models\User;
use Illuminate\Http\Request;

class FraudDetectionService
{
    public function flag(
        User $user,
        string $type,
        string $title,
        string $description,
        string $severity = 'medium',
        ?RegisteredInvoice $invoice = null,
        array $evidence = [],
        ?Request $request = null,
    ): FraudFlag {
        $flag = FraudFlag::query()->create([
            'user_id' => $user->id,
            'registered_invoice_id' => $invoice?->id,
            'flag_type' => $type,
            'source' => 'system',
            'severity' => $severity,
            'status' => 'open',
            'title' => $title,
            'description' => $description,
            'evidence' => array_filter(array_merge([
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ], $evidence), fn ($value) => $value !== null && $value !== ''),
        ]);

        Audit::log('fraud.flag.created', 'fraud_flag', $flag->id, $user, $request, [
            'flag_type' => $type,
            'severity' => $severity,
            'invoice_id' => $invoice?->id,
        ]);

        return $flag;
    }

    public function inspectApprovedInvoice(User $user, RegisteredInvoice $invoice, ?Request $request = null): void
    {
        $recentCount = RegisteredInvoice::query()
            ->where('user_id', $user->id)
            ->where('validation_status', 'approved')
            ->where('created_at', '>=', now()->subMinutes(10))
            ->count();

        if ($recentCount >= 5) {
            $this->flag(
                user: $user,
                type: 'velocity_invoice_submissions',
                title: 'Volumen inusual de facturas aprobadas',
                description: 'El participante registro 5 o mas facturas aprobadas en una ventana de 10 minutos.',
                severity: 'high',
                invoice: $invoice,
                evidence: ['recent_approved_invoices_10m' => $recentCount],
                request: $request,
            );
        }

        if ($recentCount >= 15) {
            $this->flag(
                user: $user,
                type: 'possible_internal_cashier_fraud',
                title: 'Posible fraude interno por volumen extremo',
                description: 'El participante registro 15 o mas facturas aprobadas en 10 minutos. Revisar posible uso de tickets abandonados o apoyo de cajero.',
                severity: 'critical',
                invoice: $invoice,
                evidence: ['recent_approved_invoices_10m' => $recentCount],
                request: $request,
            );
        }

        $sharedPhoneUsers = User::query()
            ->where('role', 'client')
            ->where('id', '!=', $user->id)
            ->whereNotNull('phone')
            ->where('phone', $user->phone)
            ->count();

        if ($user->phone && $sharedPhoneUsers > 0) {
            $this->flag(
                user: $user,
                type: 'shared_phone',
                title: 'Telefono compartido entre participantes',
                description: 'El telefono del participante aparece en otra cuenta de cliente.',
                severity: 'medium',
                invoice: $invoice,
                evidence: ['matching_users' => $sharedPhoneUsers],
                request: $request,
            );
        }
    }
}
