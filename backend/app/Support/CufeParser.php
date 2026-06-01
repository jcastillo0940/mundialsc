<?php

namespace App\Support;

class CufeParser
{
    public function extract(string $rawText): ?string
    {
        if (strlen($rawText) > 2048) {
            return null;
        }

        $decoded = urldecode($rawText);
        $bare    = strtoupper(trim($decoded));

        // Si el texto ya ES el CUFE directamente (p.ej. FE01200000032812-2-249262-...)
        // debe contener al menos un guion y solo chars alfanuméricos + guiones
        if (str_contains($bare, '-') && preg_match('/^[A-Z0-9][A-Z0-9\-]{15,254}$/', $bare)) {
            return $bare;
        }

        $patterns = [
            '/[?&](?:cufe|CUFE)=([A-Z0-9\-]{16,255})/i',
            '/(?:cufe|CUFE)[=:\s]+([A-Z0-9\-]{16,255})/i',
            '/(?:codigoGeneracion|codigo-generacion|claveAcceso)[=:\s]+([A-Z0-9\-]{16,255})/i',
            '/https?:\/\/\S*?[?&](?:cufe|codigoGeneracion|claveAcceso)=([A-Z0-9\-]{16,255})/i',
            '/([A-F0-9]{32,128})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $decoded, $matches)) {
                return strtoupper(trim($matches[1]));
            }
        }

        return null;
    }
}
