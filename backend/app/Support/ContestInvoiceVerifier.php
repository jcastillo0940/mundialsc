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

        $request = Http::acceptJson()->timeout(45)->connectTimeout(10);
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
                'cufe' => $apiError ?? 'No fue posible consultar la factura en este momento.',
            ]);
        }

        // La API es la autoridad — si devuelve datos los usamos sin validación adicional
        $datos = data_get($body, 'datos') ?? [];

        return [
            'cufe'             => strtoupper((string) ($datos['cufe'] ?? $cufe)),
            'invoice_number'   => strtoupper((string) ($datos['cufe'] ?? $cufe)),
            'purchase_amount'  => round((float) ($datos['total_pagado'] ?? 0), 2),
            'issued_at'        => $this->parseInvoiceDate((string) ($datos['fecha_autorizacion'] ?? '')),
            'issuer_name'      => (string) ($datos['emisor_nombre'] ?? ''),
            'payload'          => $body,
        ];
    }

    public function verify(User $user, array $payload, ?array $resolved = null): array
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
