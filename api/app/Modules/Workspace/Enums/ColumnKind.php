<?php

namespace App\Modules\Workspace\Enums;

/**
 * Semântica de uma coluna do Kanban. O status de uma tarefa é a coluna
 * em que ela está; o "kind" define o significado para indicadores.
 */
enum ColumnKind: string
{
    case OPEN = 'open';
    case WAITING = 'waiting';
    case PUBLISHING = 'publishing';
    case DONE = 'done';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Em aberto',
            self::WAITING => 'Aguardando retorno',
            self::PUBLISHING => 'Aguardando publicação',
            self::DONE => 'Concluída',
            self::CANCELLED => 'Cancelada',
        };
    }

    public function isClosed(): bool
    {
        return $this === self::DONE || $this === self::CANCELLED;
    }

    /**
     * Tipos que ainda contam como tarefa em aberto (não encerrada).
     *
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::OPEN->value, self::WAITING->value, self::PUBLISHING->value];
    }

    /**
     * @return list<string>
     */
    public static function closedValues(): array
    {
        return [self::DONE->value, self::CANCELLED->value];
    }
}
