<?php

namespace App\Modules\Notification\Mail;

use App\Modules\Notification\DTOs\NotificationMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NotificationMail extends Mailable
{
    public function __construct(
        public readonly NotificationMessage $notification,
        public readonly string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->notification->title);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.notification', with: [
            'notification' => $this->notification,
            'recipientName' => $this->recipientName,
            'appName' => config('app.name'),
        ]);
    }
}
