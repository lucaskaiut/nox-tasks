<?php

namespace App\Modules\Workspace\Enums;

/**
 * Visibilidade de tarefas, comentários, anexos e atividades.
 * INTERNAL nunca é exposto a usuários do tipo cliente.
 */
enum Visibility: string
{
    case INTERNAL = 'internal';
    case SHARED = 'shared';
}
