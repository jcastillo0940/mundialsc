<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $token,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url', config('app.url')), '/').'/login?token='.$this->token;

        return (new MailMessage)
            ->view('emails.reset-password', [
            'name' => $notifiable->name ?: 'Participante',
            'url' => $url,
            'appName' => config('app.name', 'Super Carnes'),
            'supportEmail' => config('mail.from.address'),
        ])->subject('Recupera tu contraseña');
    }
}
