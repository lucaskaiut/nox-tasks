<?php

namespace App\Modules\Notification\DTOs;

/**
 * Conteúdo de uma notificação, independente do canal. Serializável para fila:
 * contém apenas escalares e arrays.
 */
final readonly class NotificationMessage
{
    /**
     * @param  string  $kind  identificador do tipo (ex.: task_assigned)
     * @param  array<string, mixed>  $data  dados de contexto (ids, nomes) repassados aos canais
     */
    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
        public array $data = [],
        public ?int $tenantId = null,
        public ?string $actorName = null,
    ) {}
}
