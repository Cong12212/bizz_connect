<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class PasswordResetCode extends Notification
{
    use Queueable;

    public function __construct(
        public string $code,
        public int $ttlMinutes = 10
    ) {}

    public function via($notifiable) { return ['mail']; }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Your password reset code')
            ->greeting('Hi '.$notifiable->name)
            ->line('Your verification code is:')
            ->line('# '.$this->code)
            ->line('This code will expire in '.$this->ttlMinutes.' minutes.')
            ->line('If you did not request this, you can ignore this email.');
    }
}
