<?php

namespace App\Modules\ACL\Enums;

enum Permission: string
{
    case USER_CREATE = 'user.create';
    case USER_READ = 'user.read';
    case USER_UPDATE = 'user.update';
    case USER_DELETE = 'user.delete';

    case TENANT_READ = 'tenant.read';
    case TENANT_UPDATE = 'tenant.update';
    case TENANT_CREATE = 'tenant.create';

    case ROLE_CREATE = 'role.create';
    case ROLE_READ = 'role.read';
    case ROLE_UPDATE = 'role.update';
    case ROLE_DELETE = 'role.delete';

    case API_TOKEN_CREATE = 'api-token.create';
    case API_TOKEN_READ = 'api-token.read';
    case API_TOKEN_UPDATE = 'api-token.update';
    case API_TOKEN_DELETE = 'api-token.delete';

    case WEBHOOK_CREATE = 'webhook.create';
    case WEBHOOK_READ = 'webhook.read';
    case WEBHOOK_UPDATE = 'webhook.update';
    case WEBHOOK_DELETE = 'webhook.delete';

    case PLAN_CREATE = 'plan.create';
    case PLAN_READ = 'plan.read';
    case PLAN_UPDATE = 'plan.update';
    case PLAN_DELETE = 'plan.delete';

    case SUBSCRIPTION_READ = 'subscription.read';
    case SUBSCRIPTION_UPDATE = 'subscription.update';

    case INVOICE_READ = 'invoice.read';

    case AUDIT_VIEW = 'audit.view';

    case ASSISTANT_VIEW = 'assistant.view';

    // --- Projeto: Workspace (clientes, projetos, tarefas, Kanban) ---
    case CLIENT_READ = 'client.read';
    case CLIENT_CREATE = 'client.create';
    case CLIENT_UPDATE = 'client.update';
    case CLIENT_DELETE = 'client.delete';
    case CLIENT_MANAGE_USERS = 'client.manage-users';

    case PROJECT_READ = 'project.read';
    case PROJECT_CREATE = 'project.create';
    case PROJECT_UPDATE = 'project.update';
    case PROJECT_ARCHIVE = 'project.archive';

    case TASK_READ = 'task.read';
    case TASK_CREATE = 'task.create';
    case TASK_UPDATE = 'task.update';
    case TASK_DELETE = 'task.delete';
    case TASK_ASSIGN = 'task.assign';
    case TASK_MOVE = 'task.move';

    case BOARD_READ = 'board.read';
    case BOARD_MANAGE = 'board.manage';

    case COMMENT_CREATE = 'comment.create';

    case MCP_USE = 'mcp.use';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
