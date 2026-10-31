<?php

namespace App\Modules\Workspace\Enums;

enum ProjectStatus: string
{
    case PLANNING = 'planning';
    case ACTIVE = 'active';
    case ON_HOLD = 'on_hold';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PLANNING => 'Planejamento',
            self::ACTIVE => 'Em andamento',
            self::ON_HOLD => 'Pausado',
            self::COMPLETED => 'Concluído',
            self::CANCELLED => 'Cancelado',
        };
    }

    /**
     * @return list<self>
     */
    public static function inProgress(): array
    {
        return [self::PLANNING, self::ACTIVE, self::ON_HOLD];
    }
}
