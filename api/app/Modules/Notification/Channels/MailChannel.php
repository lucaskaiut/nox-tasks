<?php

namespace App\Modules\Notification\Channels;

use App\Modules\Notification\Contracts\NotificationChannel;
use App\Modules\Notification\DTOs\NotificationMessage;
use App\Modules\Notification\Mail\NotificationMail;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * E-mail via mailer padrão da aplicação (config/mail.php).
 * Roda em fila: o envio SMTP nunca atrasa a requisição do usuário.
 */
class MailChannel implements NotificationChannel
{
    public function shouldSend(User $recipient, NotificationMessage $message): bool
    {
        return filter_var($recipient->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function send(User $recipient, NotificationMessage $message): void
    {
        Mail::to($recipient->email, $recipient->name)->send(new NotificationMail($message, $recipient->name));
    }
}
