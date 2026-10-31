<?php

namespace App\Modules\Mcp\Tools\Workspace;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Support\WorkspaceTool;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Http\Resources\ActivityResource;
use App\Modules\Workspace\Http\Resources\TaskResource;
use App\Modules\Workspace\Models\Project;
use App\Modules\Workspace\Services\DashboardService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_dashboard')]
#[Description('Indicadores do usuário: projetos ativos, tarefas abertas/atrasadas/concluídas/aguardando retorno, distribuição por status e prioridade, tarefas prioritárias, próximos prazos e atividades recentes — sempre restritos ao escopo do usuário.')]
#[IsReadOnly]
class GetDashboard extends WorkspaceTool
{
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function availableTo(User $user): bool
    {
        return $user->hasPermission(Permission::PROJECT_READ);
    }

    protected function run(Request $request, User $user): array
    {
        $this->authorizeFor($user, 'viewAny', Project::class);
        $summary = app(DashboardService::class)->summary($user);

        return [
            ...$summary,
            'priority_tasks' => $this->collection(TaskResource::class, $summary['priority_tasks']),
            'upcoming_deadlines' => $this->collection(TaskResource::class, $summary['upcoming_deadlines']),
            'recent_activity' => $this->collection(ActivityResource::class, $summary['recent_activity']),
        ];
    }
}
