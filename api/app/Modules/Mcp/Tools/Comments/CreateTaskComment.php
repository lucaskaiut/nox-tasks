<?php

namespace App\Modules\Mcp\Tools\Comments;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\TaskCommentResource;
use App\Modules\Workspace\Services\CommentService;
use App\Modules\Workspace\Support\WorkspaceRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('create_task_comment')]
#[Description('Adiciona um comentário a uma tarefa. visibility: internal (padrão) ou shared (visível ao cliente). Em tarefas internas o comentário é sempre interno; comentários de usuários de cliente são sempre compartilhados.')]
class CreateTaskComment extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->format('uuid')->required(),
            'body' => $schema->string()->max(10000)->required(),
            'visibility' => $schema->string()->enum(['internal', 'shared']),
        ];
    }

    protected function availableTo(User $user): bool
    {
        return $user->hasPermission(Permission::COMMENT_CREATE);
    }

    protected function run(Request $request, User $user): array
    {
        $task = $this->authorizedTask($user, $request->get('task_id'), 'comment');
        $data = $request->validate(WorkspaceRules::comment());

        return $this->item(TaskCommentResource::class, app(CommentService::class)->create($user, $task, $data['body'], $data['visibility'] ?? null));
    }
}
