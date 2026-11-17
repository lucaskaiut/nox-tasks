<?php

namespace App\Modules\Notification\Jobs;

use App\Modules\Notification\DTOs\NotificationMessage;
use App\Modules\Notification\Services\ChannelRegistry;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Entrega uma notificação por um canal a um destinatário.
 * Um job por par canal/destinatário isola falhas e novas tentativas.
 */
class DeliverNotification implements ShouldQueue
{
    use Queueable;

    public int $tries;

    public function __construct(
        public readonly string $channel,
        public readonly int $userId,
        public readonly NotificationMessage $message,
    ) {
        $this->tries = (int) config('notifications.tries', 3);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return array_map('intval', (array) config('notifications.backoff', [60]));
    }

    public function handle(ChannelRegistry $channels): void
    {
        // Worker não tem tenant resolvido: busca explícita, sem escopo de tenant.
        // Usuários removidos (soft delete) não são encontrados e não recebem nada.
        $recipient = User::withoutTenancy()->find($this->userId);

        if ($recipient === null) {
            return;
        }

        $driver = $channels->resolve($this->channel);

        if ($driver->shouldSend($recipient, $this->message)) {
            $driver->send($recipient, $this->message);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('Notificação não entregue após novas tentativas.', [
            'channel' => $this->channel,
            'kind' => $this->message->kind,
            'user_id' => $this->userId,
            'error' => $e->getMessage(),
        ]);
    }
}
