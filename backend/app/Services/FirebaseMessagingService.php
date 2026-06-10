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

            $title     = $campaign->push_title     ?: ($campaign->name ?? '');
            $body      = $campaign->push_description ?: $title;
            $imageUrl  = $campaign->push_image_url  ?: null;
            $btnText   = $campaign->push_button_text ?: null;
            $btnUrl    = $campaign->push_button_url  ?: (string) config('app.url');

            $response = Http::withToken($accessToken)->post($endpoint, [
                'message' => [
                    'token' => $tokenRow->token,
                    'notification' => [
                        'title' => $title,
                        'body'  => $body,
                        'image' => $imageUrl,
                    ],
                    'webpush' => [
                        'fcm_options' => [
                            'link' => $btnUrl,
                        ],
                        'notification' => array_filter([
                            'title'   => $title,
                            'body'    => $body,
                            'icon'    => $imageUrl,
                            'image'   => $imageUrl,
                            'actions' => $btnText ? [[
                                'action' => 'open_link',
                                'title'  => $btnText,
                            ]] : null,
                        ]),
                    ],
                    'data' => array_filter([
                        'campaign_id' => (string) $campaign->id,
                        'button_text' => $btnText   ?: '',
                        'button_url'  => $btnUrl    ?: '',
                        'image_url'   => $imageUrl  ?: '',
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
