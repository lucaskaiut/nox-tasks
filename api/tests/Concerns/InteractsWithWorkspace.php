<?php

namespace Tests\Concerns;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Enums\ColumnKind;
use App\Modules\Workspace\Enums\Visibility;
use App\Modules\Workspace\Models\Client;
use App\Modules\Workspace\Models\Project;
use App\Modules\Workspace\Models\Subtask;
use App\Modules\Workspace\Models\Task;
use App\Modules\Workspace\Models\TaskComment;

/**
 * Cenário padrão: um tenant com um profissional e dois clientes (A e B),
 * cada um com um usuário, um projeto, uma tarefa compartilhada e uma interna.
 */
trait InteractsWithWorkspace
{
    use InteractsWithTenants;

    protected Tenant $tenant;

    protected User $professional;

    protected Client $clientA;

    protected Client $clientB;

    protected User $clientUserA;

    protected User $clientUserB;

    protected Project $projectA;

    protected Project $projectB;

    protected Task $sharedTaskA;

    protected Task $internalTaskA;

    protected Task $sharedTaskB;

    protected function setUpWorkspace(): void
    {
        $this->tenant = $this->createTenantWithRoles();
        $this->professional = $this->createProfessional($this->tenant);

        $this->clientA = Client::factory()->for($this->tenant)->create(['name' => 'Cliente A']);
        $this->clientB = Client::factory()->for($this->tenant)->create(['name' => 'Cliente B']);

        $this->clientUserA = $this->createClientUser($this->clientA);
        $this->clientUserB = $this->createClientUser($this->clientB);

        $this->projectA = Project::factory()->forClient($this->clientA)->create(['name' => 'Projeto A']);
        $this->projectB = Project::factory()->forClient($this->clientB)->create(['name' => 'Projeto B']);

        $this->sharedTaskA = Task::factory()->forProject($this->projectA)->create(['title' => 'Tarefa compartilhada A']);
        $this->internalTaskA = Task::factory()->forProject($this->projectA)->internal()->create(['title' => 'Tarefa interna A']);
        $this->sharedTaskB = Task::factory()->forProject($this->projectB)->create(['title' => 'Tarefa compartilhada B']);
    }

    protected function createProfessional(Tenant $tenant, array $attributes = []): User
    {
        $user = User::factory()->for($tenant)->create($attributes);
        $user->assignRole($this->roleFor($tenant, DefaultRole::PROFESSIONAL));

        return $user;
    }

    protected function createClientUser(Client $client, array $attributes = []): User
    {
        $user = User::factory()->create(['tenant_id' => $client->tenant_id, ...$attributes]);
        $user->forceFill(['client_id' => $client->getKey()])->save();
        $user->assignRole($this->roleFor($client->tenant, DefaultRole::CLIENT));

        return $user;
    }

    protected function addComment(Task $task, User $author, Visibility $visibility, string $body): TaskComment
    {
        $comment = new TaskComment(['body' => $body]);
        $comment->forceFill([
            'tenant_id' => $task->tenant_id,
            'task_id' => $task->getKey(),
            'user_id' => $author->getKey(),
            'visibility' => $visibility,
        ])->save();

        return $comment;
    }

    protected function addSubtask(Task $task, string $title): Subtask
    {
        $subtask = new Subtask(['title' => $title]);
        $subtask->forceFill(['tenant_id' => $task->tenant_id, 'task_id' => $task->getKey()])->save();

        return $subtask;
    }

    protected function columnOf(Project $project, ColumnKind $kind): string
    {
        return $project->board->columns()->where('kind', $kind->value)->firstOrFail()->uuid;
    }
}
