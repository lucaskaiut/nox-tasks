<?php

namespace App\Modules\Workspace\Services;

use App\Modules\Workspace\Enums\SubtaskStatus;
use App\Modules\Workspace\Enums\TaskPriority;
use App\Modules\Workspace\Models\Subtask;
use App\Modules\Workspace\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Subtarefas sempre são acessadas a partir da tarefa (autorizada antes),
 * nunca de forma isolada.
 */
class SubtaskService
{
    private const FIELDS = ['title', 'description', 'status', 'priority', 'due_at'];

    public function __construct(
        private readonly MemberService $members,
        private readonly ActivityService $activity,
        private readonly WorkspaceNotifier $notifier,
    ) {}

    /**
     * @return Collection<int, Subtask>
     */
    public function list(Task $task): Collection
    {
        return $task->subtasks()->with('assignee:id,uuid,name')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Task $task, array $data): Subtask
    {
        return DB::transaction(function () use ($task, $data): Subtask {
            $subtask = new Subtask(Arr::only($data, self::FIELDS));
            $subtask->status ??= SubtaskStatus::PENDING;
            $subtask->priority ??= TaskPriority::NORMAL;
            $subtask->task()->associate($task);
            $subtask->position = (int) $task->subtasks()->max('position') + 1;
            $subtask->assignee_id = $this->members->findProfessional($data['assignee_id'] ?? null)?->getKey();
            $this->syncCompletion($subtask);
            $subtask->save();

            $this->activity->record('subtask.created', $subtask, $task->visibility, task: $task, changes: [
                'title' => ['from' => null, 'to' => $subtask->title],
            ]);

            $this->notifyAssignee($task, $subtask);

            return $subtask->load('assignee:id,uuid,name');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Task $task, Subtask $subtask, array $data): Subtask
    {
        return DB::transaction(function () use ($task, $subtask, $data): Subtask {
            $subtask->fill(Arr::only($data, self::FIELDS));

            $assigneeChanged = false;

            if (array_key_exists('assignee_id', $data)) {
                $assigneeId = $this->members->findProfessional($data['assignee_id'])?->getKey();
                $assigneeChanged = $assigneeId !== $subtask->assignee_id && $assigneeId !== null;
                $subtask->assignee_id = $assigneeId;
            }

            $changes = $this->activity->diff($subtask, [...self::FIELDS, 'assignee_id']);
            $completed = isset($changes['status']) && $subtask->status === SubtaskStatus::DONE;
            $this->syncCompletion($subtask);
            $subtask->save();

            if ($changes !== []) {
                $this->activity->record(
                    $completed ? 'subtask.completed' : 'subtask.updated',
                    $subtask,
                    $task->visibility,
                    task: $task,
                    changes: ['subtask' => ['from' => null, 'to' => $subtask->title], ...$changes],
                );
            }

            if ($assigneeChanged) {
                $this->notifyAssignee($task, $subtask);
            }

            return $subtask->load('assignee:id,uuid,name');
        });
    }

    public function delete(Task $task, Subtask $subtask): void
    {
        DB::transaction(function () use ($task, $subtask): void {
            $subtask->delete();
            $this->activity->record('subtask.deleted', $subtask, $task->visibility, task: $task, changes: [
                'title' => ['from' => $subtask->title, 'to' => null],
            ]);
        });
    }

    private function syncCompletion(Subtask $subtask): void
    {
        $subtask->completed_at = $subtask->status === SubtaskStatus::DONE
            ? ($subtask->completed_at ?? now())
            : null;
    }

    private function notifyAssignee(Task $task, Subtask $subtask): void
    {
        if ($subtask->assignee_id === null) {
            return;
        }

        $this->notifier->aboutTask(
            $task,
            [$subtask->assignee()->first()],
            'task_assigned',
            'Subtarefa atribuída',
            "Você é o responsável pela subtarefa \"{$subtask->title}\" em \"{$task->title}\".",
            internal: true,
        );
    }
}
