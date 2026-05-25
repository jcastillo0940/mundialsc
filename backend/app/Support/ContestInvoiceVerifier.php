<?php

namespace App\Support;

use App\Models\InvoiceGoalSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class ContestInvoiceVerifier
{
    public function resolve(string $cufe): array
    {
        $endpoint = config('contest.dgi_verifier_url');

        if (! $endpoint) {
            throw ValidationException::withMessages([
                'cufe' => 'La verificacion automatica de facturas no esta configurada.',
            ]);
        }

        $request = Http::acceptJson();
        $token = (string) config('contest.dgi_verifier_token');

        if ($token !== '') {
            $request = $request->withToken($token);
        }

        $response = $request->get($endpoint, [
            'cufe' => $cufe,
        ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'cufe' => 'No fue posible consultar la factura con DGI en este momento.',
            ]);
        }

        /** @var array<string, mixed> $body */
        $body = $response->json();
        $datos = data_get($body, 'datos');

        if (! is_array($datos) || empty($datos['cufe'])) {
            throw ValidationException::withMessages([
                'cufe' => 'La respuesta de DGI no incluyo un CUFE valido para esta factura.',
            ]);
        }

        $canonicalCufe = strtoupper((string) $datos['cufe']);
        $issuedAt = $this->parseInvoiceDate((string) ($datos['fecha_autorizacion'] ?? ''));
        $purchaseAmount = round((float) ($datos['total_pagado'] ?? 0), 2);

        if ($purchaseAmount <= 0) {
            throw ValidationException::withMessages([
                'cufe' => 'La respuesta de DGI no incluyo un monto total valido para esta factura.',
            ]);
        }

        return [
            'cufe' => $canonicalCufe,
            'invoice_number' => $canonicalCufe,
            'purchase_amount' => $purchaseAmount,
            'issued_at' => $issuedAt,
            'issuer_name' => (string) ($datos['emisor_nombre'] ?? ''),
            'payload' => $body,
        ];
    }

    public function verify(User $user, array $payload): array
    {
        $settings = InvoiceGoalSetting::query()->first();
        $validationMode = $settings?->validation_mode ?? 'manual';
        $endpoint = config('contest.dgi_verifier_url');

        if ($validationMode === 'manual' || ! $endpoint) {
            return [
                'status' => 'approved',
                'notes' => 'Factura aprobada con validacion interna. La integracion DGI queda desacoplada por ahora.',
                'canonical_cufe' => $payload['cufe'],
                'payload' => null,
            ];
        }

        try {
            $resolved = $this->resolve($payload['cufe']);
        } catch (ValidationException) {
            return [
                'status' => 'pending',
                'notes' => 'No fue posible confirmar la factura con DGI en este momento.',
                'canonical_cufe' => $payload['cufe'],
                'payload' => null,
            ];
        }

        $body = $resolved['payload'];
        $canonicalCufe = (string) $resolved['cufe'];

        if ($canonicalCufe !== '') {
            return [
                'status' => 'approved',
                'notes' => 'Factura validada contra DGI.',
                'canonical_cufe' => strtoupper($canonicalCufe),
                'payload' => $body,
            ];
        }

        $status = strtolower((string) ($body['status'] ?? ''));
        $isValid = filter_var($body['valid'] ?? false, FILTER_VALIDATE_BOOL);
        $ownerMatches = ! array_key_exists('owner_matches', $body)
            || filter_var($body['owner_matches'], FILTER_VALIDATE_BOOL);

        if (! $isValid) {
            return [
                'status' => 'rejected',
                'notes' => (string) ($body['notes'] ?? 'DGI marco el CUFE como invalido.'),
                'canonical_cufe' => $payload['cufe'],
                'payload' => $body,
            ];
        }

        if (! $ownerMatches || $status === 'mismatch') {
            return [
                'status' => 'disqualify',
                'notes' => (string) ($body['notes'] ?? 'La factura no pertenece al participante registrado.'),
                'canonical_cufe' => $payload['cufe'],
                'payload' => $body,
            ];
        }

        return [
            'status' => 'approved',
            'notes' => (string) ($body['notes'] ?? 'Factura validada contra DGI.'),
            'canonical_cufe' => $payload['cufe'],
            'payload' => $body,
        ];
    }

    private function parseInvoiceDate(string $rawDate): CarbonImmutable
    {
        $timezone = 'America/Panama';
        $trimmed = trim($rawDate);

        if ($trimmed === '') {
            return now($timezone)->toImmutable();
        }

        $formats = ['d/m/Y H:i:s', 'Y-m-d H:i:s', DATE_ATOM];

        foreach ($formats as $format) {
            try {
                return CarbonImmutable::createFromFormat($format, $trimmed, $timezone);
            } catch (\Throwable) {
                continue;
            }
        }

        return CarbonImmutable::parse($trimmed, $timezone);
    }
}
