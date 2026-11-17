<?php

namespace App\Modules\Workspace\Services;

use App\Modules\Notification\DTOs\NotificationMessage;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Models\Project;
use App\Modules\Workspace\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Seleciona os destinatários das notificações do Workspace respeitando as
 * mesmas Policies da aplicação: um destinatário só é notificado se puder
 * visualizar o recurso, e conteúdo interno nunca vai a usuários de cliente.
 * A entrega (in-app, e-mail...) fica a cargo do NotificationDispatcher.
 */
class WorkspaceNotifier
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

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
            $this->url("/w/tasks/{$task->uuid}"),
            'Abrir tarefa',
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
            $this->url("/w/projects/{$project->uuid}"),
            'Abrir projeto',
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
    private function send(Collection $recipients, string $kind, string $title, string $message, string $url, string $label, array $payload): void
    {
        if ($recipients->isEmpty()) {
            return;
        }

        $actor = auth()->user();

        $this->dispatcher->send($recipients, new NotificationMessage(
            kind: $kind,
            title: $title,
            body: $message,
            actionUrl: $url,
            actionLabel: $label,
            data: $payload,
            tenantId: TenantContext::tenantId(),
            actorName: $actor instanceof User ? $actor->name : null,
        ));
    }

    private function url(string $path): string
    {
        return config('notifications.frontend_url').$path;
    }
}
