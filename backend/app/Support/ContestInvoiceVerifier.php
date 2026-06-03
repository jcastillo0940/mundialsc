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

        $request = Http::acceptJson()->timeout(45)->connectTimeout(10)->withHeaders(['Connection' => 'close']);
        $token = (string) config('contest.dgi_verifier_token');

        if ($token !== '') {
            $request = $request->withToken($token);
        }

        $response = $request->get($endpoint, [
            'cufe' => $cufe,
        ]);

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        // Si la API devuelve un error (HTTP o en el cuerpo), lo mostramos tal cual al usuario
        $apiError = data_get($body, 'error') ?? data_get($body, 'mensaje') ?? data_get($body, 'message');

        if (! $response->successful() || $apiError) {
            throw ValidationException::withMessages([
                'cufe' => $apiError ? 'La factura no pudo ser validada por DGI.' : 'No fue posible consultar la factura en este momento.',
            ]);
        }

        // La API es la autoridad — si devuelve datos los usamos sin validación adicional
        $datos = data_get($body, 'datos') ?? [];

        return [
            'cufe'             => strtoupper((string) ($datos['cufe'] ?? $cufe)),
            'invoice_number'   => strtoupper((string) ($datos['cufe'] ?? $cufe)),
            'purchase_amount'  => round((float) ($datos['total_pagado'] ?? 0), 2),
            'issued_at'        => $this->parseInvoiceDate((string) ($datos['fecha_autorizacion'] ?? '')),
            'issuer_ruc'        => (string) ($datos['emisor_ruc'] ?? $datos['ruc_emisor'] ?? $datos['ruc'] ?? ''),
            'issuer_name'      => (string) ($datos['emisor_nombre'] ?? ''),
            'payload'          => $body,
        ];
    }

    public function verify(User $user, array $payload, ?array $resolved = null): array
    {
        $settings = InvoiceGoalSetting::query()->first();
        $validationMode = $settings?->validation_mode ?? 'manual';
        $endpoint = config('contest.dgi_verifier_url');

        if ($validationMode !== 'api' || ! $endpoint) {
            return [
                'status' => 'rejected',
                'notes' => 'La validacion oficial exige confirmacion directa contra DGI.',
                'canonical_cufe' => $payload['cufe'],
                'payload' => null,
            ];
        }

        if ($resolved === null) {
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
        }

        $body = $resolved['payload'];
        $canonicalCufe = (string) $resolved['cufe'];
        $status = strtolower((string) data_get($body, 'status', data_get($body, 'datos.status', '')));
        $isValid = data_get($body, 'valid', data_get($body, 'datos.valid'));
        $ownerMatches = data_get($body, 'owner_matches', data_get($body, 'datos.owner_matches'));
        $notes = (string) (data_get($body, 'notes') ?? data_get($body, 'datos.notes') ?? 'Factura validada contra DGI.');

        if (in_array($status, ['invalid', 'rejected', 'failed'], true) || ($isValid !== null && ! filter_var($isValid, FILTER_VALIDATE_BOOL))) {
            return [
                'status' => 'disqualify',
                'notes' => $notes !== 'Factura validada contra DGI.' ? $notes : 'DGI marco el CUFE como invalido.',
                'canonical_cufe' => $payload['cufe'],
                'payload' => $body,
            ];
        }

        if ($status === 'mismatch' || ($ownerMatches !== null && ! filter_var($ownerMatches, FILTER_VALIDATE_BOOL))) {
            return [
                'status' => 'disqualify',
                'notes' => $notes !== 'Factura validada contra DGI.' ? $notes : 'La factura no pertenece al participante registrado.',
                'canonical_cufe' => $payload['cufe'],
                'payload' => $body,
            ];
        }

        if ($canonicalCufe === '') {
            return [
                'status' => 'pending',
                'notes' => 'DGI no devolvio un CUFE canonico para confirmar la factura.',
                'canonical_cufe' => $payload['cufe'],
                'payload' => $body,
            ];
        }

        return [
            'status' => 'approved',
            'notes' => $notes,
            'canonical_cufe' => strtoupper($canonicalCufe),
            'payload' => $body,
        ];
    }

    private function parseInvoiceDate(string $rawDate): CarbonImmutable
    {
        $timezone = 'America/Panama';
        $trimmed = trim($rawDate);

        if ($trimmed === '') {
            throw ValidationException::withMessages([
                'issued_at' => 'DGI no devolvio una fecha de emision valida para la factura.',
            ]);
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
