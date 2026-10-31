<?php

namespace App\Modules\Workspace\Services;

use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Models\Project;
use App\Modules\Workspace\Models\Task;
use App\Modules\Workspace\Notifications\WorkspaceNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

/**
 * Entrega notificações respeitando as mesmas Policies da aplicação:
 * um destinatário só é notificado se puder visualizar o recurso. Conteúdo
 * interno nunca é enviado a usuários de cliente.
 */
class WorkspaceNotifier
{
    /**
     * @param  iterable<User|null>  $recipients
     */
    public function aboutTask(
        Task $task,
        iterable $recipients,
        string $kind,
        string $title,
        string $message,
        bool $internal = false,
    ): void {
        $task->loadMissing('project');

        $this->send(
            $this->filter($recipients, fn (User $user) => Gate::forUser($user)->allows('view', $task), $internal),
            $kind,
            $title,
            $message,
            [
                'task_id' => $task->uuid,
                'task_title' => $task->title,
                'project_id' => $task->project?->uuid,
                'project_name' => $task->project?->name,
            ],
        );
    }

    /**
     * @param  iterable<User|null>  $recipients
     */
    public function aboutProject(
        Project $project,
        iterable $recipients,
        string $kind,
        string $title,
        string $message,
        bool $internal = false,
    ): void {
        $this->send(
            $this->filter($recipients, fn (User $user) => Gate::forUser($user)->allows('view', $project), $internal),
            $kind,
            $title,
            $message,
            [
                'project_id' => $project->uuid,
                'project_name' => $project->name,
            ],
        );
    }

    /**
     * Usuários vinculados ao cliente do projeto.
     *
     * @return Collection<int, User>
     */
    public function clientUsersOf(Project $project): Collection
    {
        return User::query()->where('client_id', $project->client_id)->get();
    }

    /**
     * @param  iterable<User|null>  $recipients
     * @param  callable(User): bool  $canView
     * @return Collection<int, User>
     */
    private function filter(iterable $recipients, callable $canView, bool $internal): Collection
    {
        $actorId = auth()->id();

        return collect($recipients)
            ->filter(fn ($user) => $user instanceof User && ! $user->trashed())
            ->unique(fn (User $user) => $user->getKey())
            ->reject(fn (User $user) => $user->getKey() === $actorId)
            ->reject(fn (User $user) => $internal && $user->isClientUser())
            ->filter($canView)
            ->values();
    }

    /**
     * @param  Collection<int, User>  $recipients
     * @param  array<string, mixed>  $payload
     */
    private function send(Collection $recipients, string $kind, string $title, string $message, array $payload): void
    {
        if ($recipients->isEmpty()) {
            return;
        }

        $actor = auth()->user();

        Notification::send($recipients, new WorkspaceNotification($kind, $title, $message, [
            ...$payload,
            'tenant_id' => TenantContext::tenantId(),
            'actor' => $actor instanceof User ? $actor->name : null,
        ]));
    }
}
