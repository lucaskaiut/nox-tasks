<?php

namespace App\Modules\Notification\Services;

use App\Modules\Notification\Contracts\NotificationChannel;
use App\Modules\Notification\DTOs\NotificationMessage;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Catálogo dos canais configurados em config/notifications.php.
 */
class ChannelRegistry
{
    /**
     * @param  array<string, array{driver: class-string<NotificationChannel>, enabled?: bool, queue?: bool, kinds?: list<string>|null}>  $channels
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $channels,
    ) {}

    /**
     * Nomes dos canais habilitados que atendem ao tipo da mensagem.
     *
     * @return list<string>
     */
    public function channelsFor(NotificationMessage $message): array
    {
        $names = [];

        foreach ($this->channels as $name => $config) {
            $kinds = $config['kinds'] ?? null;

            if (($config['enabled'] ?? true) && ($kinds === null || in_array($message->kind, $kinds, true))) {
                $names[] = $name;
            }
        }

        return $names;
    }

    public function isQueued(string $name): bool
    {
        return (bool) ($this->config($name)['queue'] ?? false);
    }

    public function resolve(string $name): NotificationChannel
    {
        $channel = $this->container->make($this->config($name)['driver']);

        if (! $channel instanceof NotificationChannel) {
            throw new InvalidArgumentException("O canal de notificação [{$name}] não implementa ".NotificationChannel::class.'.');
        }

        return $channel;
    }

    /**
     * @return array<string, mixed>
     */
    private function config(string $name): array
    {
        return $this->channels[$name] ?? throw new InvalidArgumentException("Canal de notificação [{$name}] não configurado.");
    }
}
