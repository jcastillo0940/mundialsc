<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MailLog;
use App\Models\NewsletterSubscriber;
use App\Notifications\NewsletterConfirmationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class NewsletterController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
        ]);

        $token = Str::random(64);

        $subscriber = NewsletterSubscriber::query()->updateOrCreate(
            ['email' => Str::lower($data['email'])],
            [
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
                'confirmed_at' => null,
                'confirmation_token_hash' => Hash::make($token),
                'confirmation_sent_at' => now(),
                'source' => 'website',
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ],
        );

        $subscriber->notify(new NewsletterConfirmationNotification($token));

        MailLog::query()->create([
            'channel' => 'newsletter',
            'event_type' => 'queued',
            'recipient_email' => $subscriber->email,
            'subject' => 'Confirma tu suscripción al newsletter',
            'status' => 'queued',
            'subscriber_id' => $subscriber->id,
            'meta' => [
                'action' => 'subscribe_request',
            ],
            'sent_at' => now(),
        ]);

        return response()->json([
            'message' => 'Si tu correo es válido, recibirás un enlace para confirmar tu suscripción.',
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $subscriber = NewsletterSubscriber::query()
            ->whereNotNull('confirmation_token_hash')
            ->whereNull('confirmed_at')
            ->whereNull('unsubscribed_at')
            ->get()
            ->first(fn (NewsletterSubscriber $candidate) => Hash::check($data['token'], (string) $candidate->confirmation_token_hash));

        if (! $subscriber) {
            return response()->json([
                'message' => 'No pudimos confirmar tu suscripción. Solicita un nuevo enlace.',
            ], 422);
        }

        $subscriber->forceFill([
            'confirmed_at' => now(),
            'confirmation_token_hash' => null,
            'confirmation_sent_at' => null,
        ])->save();

        MailLog::query()->create([
            'channel' => 'newsletter',
            'event_type' => 'confirmed',
            'recipient_email' => $subscriber->email,
            'subject' => 'Confirma tu suscripción al newsletter',
            'status' => 'confirmed',
            'subscriber_id' => $subscriber->id,
            'meta' => [
                'action' => 'confirmed',
            ],
            'sent_at' => now(),
        ]);

        return response()->json([
            'message' => 'Tu suscripción al newsletter quedó confirmada.',
        ]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
        ]);

        $subscriber = NewsletterSubscriber::query()->where('email', Str::lower($data['email']))->first();

        if ($subscriber) {
            $subscriber->forceFill([
                'unsubscribed_at' => now(),
                'confirmed_at' => null,
                'confirmation_token_hash' => null,
                'confirmation_sent_at' => null,
            ])->save();
        }

        if ($subscriber) {
            MailLog::query()->create([
                'channel' => 'newsletter',
                'event_type' => 'unsubscribed',
                'recipient_email' => $subscriber->email,
                'subject' => 'Newsletter baja',
                'status' => 'unsubscribed',
                'subscriber_id' => $subscriber->id,
                'meta' => [
                    'action' => 'unsubscribe',
                ],
                'sent_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Tu correo fue retirado del newsletter.',
        ]);
    }
}
