<?php

namespace App\Modules\Workspace\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Models\Project;
use App\Modules\Workspace\Models\Task;
use App\Modules\Workspace\Policies\Concerns\AuthorizesWorkspace;
use Illuminate\Auth\Access\Response;

/**
 * Também governa subtarefas, comentários e anexos: eles herdam o acesso
 * da tarefa à qual pertencem.
 */
class TaskPolicy
{
    use AuthorizesWorkspace;

    public function __construct(private readonly ProjectPolicy $projects) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::TASK_READ);
    }

    public function view(User $user, Task $task): Response
    {
        return $this->readable($this->visible($user, $task));
    }

    /**
     * Criação em um projeto: o projeto precisa estar visível e ativo.
     * Clientes só criam tarefas nos projetos do próprio cliente.
     */
    public function create(User $user, Project $project): Response
    {
        return $this->act(
            $this->projects->visible($user, $project),
            $user->hasPermission(Permission::TASK_CREATE) && ! $project->isArchived(),
        );
    }

    public function update(User $user, Task $task): Response
    {
        return $this->act($this->visible($user, $task), $this->professional($user, Permission::TASK_UPDATE));
    }

    public function delete(User $user, Task $task): Response
    {
        return $this->act($this->visible($user, $task), $this->professional($user, Permission::TASK_DELETE));
    }

    public function assign(User $user, Task $task): Response
    {
        return $this->act($this->visible($user, $task), $this->professional($user, Permission::TASK_ASSIGN));
    }

    /**
     * Definir responsável/participantes já na criação da tarefa.
     */
    public function assignOnCreate(User $user): bool
    {
        return $this->professional($user, Permission::TASK_ASSIGN);
    }

    public function move(User $user, Task $task): Response
    {
        return $this->act($this->visible($user, $task), $this->professional($user, Permission::TASK_MOVE));
    }

    public function comment(User $user, Task $task): Response
    {
        return $this->act($this->visible($user, $task), $user->hasPermission(Permission::COMMENT_CREATE));
    }

    public function visible(User $user, Task $task): bool
    {
        if (! $this->inTenant($task) || ! $user->hasPermission(Permission::TASK_READ)) {
            return false;
        }

        if (! $user->isClientUser()) {
            return true;
        }

        $task->loadMissing('project');

        return $task->isShared()
            && $task->project !== null
            && (int) $task->project->client_id === (int) $user->client_id;
    }
}
