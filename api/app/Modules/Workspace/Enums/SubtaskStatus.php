<?php

namespace App\Modules\Workspace\Enums;

enum SubtaskStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case DONE = 'done';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::IN_PROGRESS => 'Em andamento',
            self::DONE => 'Concluída',
        };
    }
}
