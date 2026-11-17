<?php

namespace App\Modules\Notification\Services;

use App\Modules\Notification\DTOs\NotificationMessage;
use App\Modules\Notification\Jobs\DeliverNotification;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ponto único de disparo de notificações do sistema.
 *
 * Quem dispara não conhece os canais: entrega a mensagem e os destinatários,
 * e o dispatcher distribui para cada canal habilitado. Canais em fila são
 * enviados só após o commit da transação corrente; falha em um canal ou
 * destinatário não afeta os demais.
 */
class NotificationDispatcher
{
    public function __construct(private readonly ChannelRegistry $channels) {}

    /**
     * @param  iterable<User>  $recipients
     */
    public function send(iterable $recipients, NotificationMessage $message): void
    {
        $recipients = collect($recipients)->filter(fn ($user) => $user instanceof User)->values();

        if ($recipients->isEmpty()) {
            return;
        }

        foreach ($this->channels->channelsFor($message) as $name) {
            foreach ($recipients as $recipient) {
                $this->channels->isQueued($name)
                    ? $this->queue($name, $recipient, $message)
                    : $this->deliverNow($name, $recipient, $message);
            }
        }
    }

    private function queue(string $channel, User $recipient, NotificationMessage $message): void
    {
        DeliverNotification::dispatch($channel, (int) $recipient->getKey(), $message)
            ->onQueue(config('notifications.queue'))
            ->afterCommit();
    }

    private function deliverNow(string $channel, User $recipient, NotificationMessage $message): void
    {
        try {
            $driver = $this->channels->resolve($channel);

            if ($driver->shouldSend($recipient, $message)) {
                $driver->send($recipient, $message);
            }
        } catch (Throwable $e) {
            Log::warning('Falha ao entregar notificação.', [
                'channel' => $channel,
                'kind' => $message->kind,
                'user_id' => $recipient->getKey(),
                'error' => $e->getMessage(),
            ]);
            report($e);
        }
    }
}
