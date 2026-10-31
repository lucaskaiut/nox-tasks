<?php

namespace App\Modules\Workspace\Services;

use App\Modules\User\Models\User;
use App\Modules\Workspace\Enums\Visibility;
use App\Modules\Workspace\Models\Task;
use App\Modules\Workspace\Models\TaskComment;
use App\Modules\Workspace\Support\OperationContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CommentService
{
    public function __construct(
        private readonly ActivityService $activity,
        private readonly WorkspaceNotifier $notifier,
        private readonly TaskService $tasks,
        private readonly OperationContext $context,
    ) {}

    public function paginate(User $actor, Task $task, int $perPage = 30): LengthAwarePaginator
    {
        return $task->comments()
            ->visibleTo($actor)
            ->with('author:id,uuid,name,client_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * Comentários de clientes são sempre compartilhados; comentários em
     * tarefas internas são sempre internos.
     */
    public function create(User $actor, Task $task, string $body, ?string $visibility = null): TaskComment
    {
        return DB::transaction(function () use ($actor, $task, $body, $visibility): TaskComment {
            $resolved = match (true) {
                $actor->isClientUser() => Visibility::SHARED,
                ! $task->isShared() => Visibility::INTERNAL,
                default => Visibility::tryFrom((string) $visibility) ?? Visibility::INTERNAL,
            };

            $comment = new TaskComment(['body' => $body]);
            $comment->visibility = $resolved;
            $comment->source = $this->context->source();
            $comment->task()->associate($task);
            $comment->author()->associate($actor);
            $comment->save();

            $this->activity->record('comment.created', $comment, $resolved, task: $task);

            $this->notifier->aboutTask(
                $task,
                $this->tasks->stakeholders($task, includeClient: $resolved === Visibility::SHARED)
                    ->concat($actor->isClientUser() ? $task->project->members()->get() : []),
                'task_commented',
                'Novo comentário',
                "{$actor->name} comentou em \"{$task->title}\".",
                internal: $resolved === Visibility::INTERNAL,
            );

            return $comment->load('author:id,uuid,name,client_id');
        });
    }
}
