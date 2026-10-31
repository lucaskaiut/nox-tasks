<?php

namespace App\Modules\Mcp\Tools\Tasks;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\TaskResource;
use App\Modules\Workspace\Services\TaskService;
use App\Modules\Workspace\Support\WorkspaceRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('assign_task')]
#[Description('Define o responsável principal (assignee_id; null remove) e, opcionalmente, substitui os participantes. Apenas profissionais podem ser atribuídos (list_members).')]
class AssignTask extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->format('uuid')->required(),
            'assignee_id' => $schema->string()->format('uuid')->nullable()->required(),
            'participant_ids' => $schema->array()->items($schema->string()->format('uuid'))->max(20),
        ];
    }

    protected function availableTo(User $user): bool
    {
        return ! $user->isClientUser() && $user->hasPermission(Permission::TASK_ASSIGN);
    }

    protected function run(Request $request, User $user): array
    {
        $task = $this->authorizedTask($user, $request->get('task_id'), 'assign');

        $data = $request->validate(WorkspaceRules::taskAssignment());

        return $this->item(TaskResource::class, app(TaskService::class)->assign(
            $user,
            $task,
            $data['assignee_id'],
            array_key_exists('participant_ids', $data) ? $data['participant_ids'] : null,
        ));
    }
}
