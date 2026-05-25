<?php

namespace App\Support;

class CufeParser
{
    public function extract(string $rawText): ?string
    {
        $decoded = urldecode($rawText);
        $patterns = [
            '/(?:cufe|CUFE)[=:\s]+([A-Z0-9\-]{16,255})/i',
            '/[?&](?:cufe|CUFE)=([A-Z0-9\-]{16,255})/i',
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
