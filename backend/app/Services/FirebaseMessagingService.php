<?php

namespace App\Services;

use App\Models\FcmToken;
use App\Models\Campaign;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FirebaseMessagingService
{
    public function sendCampaign(Campaign $campaign, iterable $tokens): array
    {
        $projectId = (string) config('services.firebase.project_id', '');

        if ($projectId === '') {
            throw new RuntimeException('Firebase project_id no esta configurado.');
        }

        $accessToken = $this->getAccessToken();
        $endpoint = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $sent = 0;
        $failed = 0;

        foreach ($tokens as $tokenRow) {
            if (! $tokenRow instanceof FcmToken || trim((string) $tokenRow->token) === '') {
                $failed++;
                continue;
            }

            $response = Http::withToken($accessToken)->post($endpoint, [
                'message' => [
                    'token' => $tokenRow->token,
                    'notification' => [
                        'title' => $campaign->title,
                        'body' => $campaign->description ?: $campaign->title,
                        'image' => $campaign->image_url ?: null,
                    ],
                    'webpush' => [
                        'fcm_options' => [
                            'link' => $campaign->button_url ?: (string) config('app.url'),
                        ],
                        'notification' => array_filter([
                            'title' => $campaign->title,
                            'body' => $campaign->description ?: $campaign->title,
                            'icon' => $campaign->image_url ?: null,
                            'image' => $campaign->image_url ?: null,
                            'actions' => $campaign->button_text && $campaign->button_url ? [[
                                'action' => 'open_link',
                                'title' => $campaign->button_text,
                            ]] : null,
                        ]),
                    ],
                    'data' => array_filter([
                        'campaign_id' => (string) $campaign->id,
                        'button_text' => $campaign->button_text ?: '',
                        'button_url' => $campaign->button_url ?: '',
                        'image_url' => $campaign->image_url ?: '',
                    ]),
                ],
            ]);

            if ($response->successful()) {
                $sent++;
                $tokenRow->forceFill(['last_seen_at' => now(), 'is_enabled' => true])->save();
                continue;
            }

            $failed++;
            if (str_contains((string) $response->body(), 'UNREGISTERED') || str_contains((string) $response->body(), 'registration-token-not-registered')) {
                $tokenRow->forceFill(['is_enabled' => false])->save();
            }
        }

        return compact('sent', 'failed');
    }

    private function getAccessToken(): string
    {
        $serviceAccount = json_decode((string) config('services.firebase.service_account_json', ''), true);

        if (! is_array($serviceAccount) || empty($serviceAccount['client_email']) || empty($serviceAccount['private_key'])) {
            throw new RuntimeException('Firebase service account no configurado.');
        }

        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claimSet = $this->base64UrlEncode(json_encode([
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signature = '';
        openssl_sign($header.'.'.$claimSet, $signature, $serviceAccount['private_key'], OPENSSL_ALGO_SHA256);
        $jwt = $header.'.'.$claimSet.'.'.$this->base64UrlEncode($signature);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (! $response->successful() || empty($response['access_token'])) {
            throw new RuntimeException('No se pudo obtener access token de Firebase.');
        }

        return (string) $response['access_token'];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
