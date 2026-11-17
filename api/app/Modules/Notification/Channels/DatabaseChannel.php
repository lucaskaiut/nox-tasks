<?php

namespace App\Modules\Notification\Channels;

use App\Modules\Notification\Contracts\NotificationChannel;
use App\Modules\Notification\DTOs\NotificationMessage;
use App\Modules\User\Models\User;
use Illuminate\Support\Str;

/**
 * Central de notificações in-app (tabela notifications), lida por
 * NotificationController. Mantém o formato de `data` consumido pelo front-end.
 */
class DatabaseChannel implements NotificationChannel
{
    public function shouldSend(User $recipient, NotificationMessage $message): bool
    {
        return true;
    }

    public function send(User $recipient, NotificationMessage $message): void
    {
        $recipient->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => $message->kind,
            'data' => [
                'kind' => $message->kind,
                'title' => $message->title,
                'message' => $message->body,
                ...$message->data,
                'tenant_id' => $message->tenantId,
                'actor' => $message->actorName,
            ],
        ]);
    }
}
