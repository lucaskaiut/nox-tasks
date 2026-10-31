<?php

namespace App\Modules\Workspace\Enums;

enum TaskPriority: string
{
    case LOW = 'low';
    case NORMAL = 'normal';
    case HIGH = 'high';
    case URGENT = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Baixa',
            self::NORMAL => 'Normal',
            self::HIGH => 'Alta',
            self::URGENT => 'Urgente',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::LOW => 1,
            self::NORMAL => 2,
            self::HIGH => 3,
            self::URGENT => 4,
        };
    }
}
