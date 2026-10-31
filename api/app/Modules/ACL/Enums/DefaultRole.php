<?php

namespace App\Modules\ACL\Enums;

enum DefaultRole: string
{
    case ADMINISTRATOR = 'Administrador';
    case USER = 'Usuário';
    case PROFESSIONAL = 'Profissional';
    case CLIENT = 'Cliente';

    public function description(): string
    {
        return match ($this) {
            self::ADMINISTRATOR => 'Acesso completo ao tenant.',
            self::USER => 'Acesso básico de leitura.',
            self::PROFESSIONAL => 'Gerencia clientes, projetos, tarefas e quadros.',
            self::CLIENT => 'Acompanha os projetos do próprio cliente e abre tarefas.',
        };
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::ADMINISTRATOR => Permission::cases(),
            self::USER => [
                Permission::USER_READ,
            ],
            self::PROFESSIONAL => [
                Permission::USER_READ,
                Permission::CLIENT_READ,
                Permission::CLIENT_CREATE,
                Permission::CLIENT_UPDATE,
                Permission::CLIENT_MANAGE_USERS,
                Permission::PROJECT_READ,
                Permission::PROJECT_CREATE,
                Permission::PROJECT_UPDATE,
                Permission::PROJECT_ARCHIVE,
                Permission::TASK_READ,
                Permission::TASK_CREATE,
                Permission::TASK_UPDATE,
                Permission::TASK_DELETE,
                Permission::TASK_ASSIGN,
                Permission::TASK_MOVE,
                Permission::BOARD_READ,
                Permission::BOARD_MANAGE,
                Permission::COMMENT_CREATE,
                Permission::MCP_USE,
            ],
            // Escopo de dados do cliente é imposto pelas Policies (users.client_id),
            // independentemente das permissões concedidas ao papel.
            self::CLIENT => [
                Permission::PROJECT_READ,
                Permission::TASK_READ,
                Permission::TASK_CREATE,
                Permission::BOARD_READ,
                Permission::COMMENT_CREATE,
                Permission::MCP_USE,
            ],
        };
    }
}
