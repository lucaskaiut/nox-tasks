<?php

namespace App\Modules\Workspace\Services;

use App\Modules\User\Models\User;
use App\Modules\Workspace\Enums\Visibility;
use App\Modules\Workspace\Models\Activity;
use App\Modules\Workspace\Models\Project;
use App\Modules\Workspace\Models\Task;
use App\Modules\Workspace\Support\OperationContext;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ActivityService
{
    public function __construct(private readonly OperationContext $context) {}

    /**
     * @param  array<string, array{from: mixed, to: mixed}>|null  $changes
     */
    public function record(
        string $event,
        Model $subject,
        Visibility $visibility,
        ?Project $project = null,
        ?Task $task = null,
        ?array $changes = null,
    ): Activity {
        $actor = auth()->user();

        return Activity::query()->create([
            'user_id' => $actor instanceof User ? $actor->getKey() : null,
            'project_id' => $project?->getKey() ?? $task?->project_id,
            'task_id' => $task?->getKey(),
            'subject_type' => Str::snake(class_basename($subject)),
            'subject_id' => (string) ($subject->getAttribute('uuid') ?? $subject->getKey()),
            'event' => $event,
            'changes' => $changes ?: null,
            'visibility' => $visibility,
            'source' => $this->context->source(),
            'created_at' => now(),
        ]);
    }

    /**
     * Captura valores anteriores/novos dos atributos sujos (chamar antes do save).
     *
     * @param  list<string>  $fields
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public function diff(Model $model, array $fields): array
    {
        $changes = [];

        foreach ($fields as $field) {
            if (! $model->isDirty($field)) {
                continue;
            }

            $from = $this->normalize($model->getOriginal($field));
            $to = $this->normalize($model->getAttribute($field));

            if ($from !== $to) {
                $changes[$field] = ['from' => $from, 'to' => $to];
            }
        }

        return $changes;
    }

    public function forTask(User $actor, Task $task, int $perPage = 20): LengthAwarePaginator
    {
        return Activity::query()
            ->visibleTo($actor)
            ->where('task_id', $task->getKey())
            ->with('user:id,uuid,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($perPage));
    }

    public function forProject(User $actor, Project $project, int $perPage = 20): LengthAwarePaginator
    {
        return Activity::query()
            ->visibleTo($actor)
            ->where('project_id', $project->getKey())
            ->with(['user:id,uuid,name', 'task:id,uuid,title'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($perPage));
    }

    /**
     * @return Collection<int, Activity>
     */
    public function recent(User $actor, int $limit = 10): Collection
    {
        return Activity::query()
            ->visibleTo($actor)
            ->whereNotNull('project_id')
            ->with(['user:id,uuid,name', 'task:id,uuid,title', 'project:id,uuid,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    private function normalize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            default => $value,
        };
    }

    private function perPage(int $perPage): int
    {
        return min(max($perPage, 1), 100);
    }
}
