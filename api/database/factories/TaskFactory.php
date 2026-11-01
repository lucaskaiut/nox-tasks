<?php

namespace Database\Factories;

use App\Modules\Workspace\Enums\ColumnKind;
use App\Modules\Workspace\Enums\TaskPriority;
use App\Modules\Workspace\Enums\Visibility;
use App\Modules\Workspace\Models\Project;
use App\Modules\Workspace\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'priority' => TaskPriority::NORMAL,
            'visibility' => Visibility::SHARED,
        ];
    }

    public function forProject(Project $project, ColumnKind $kind = ColumnKind::OPEN): static
    {
        return $this->state(function () use ($project, $kind): array {
            $column = BoardFactory::generalFor((int) $project->tenant_id)
                ->columns()->where('kind', $kind->value)->firstOrFail();

            return [
                'tenant_id' => $project->tenant_id,
                'project_id' => $project->getKey(),
                'board_column_id' => $column->getKey(),
                'completed_at' => $kind === ColumnKind::DONE ? now() : null,
            ];
        });
    }

    public function internal(): static
    {
        return $this->state(fn (): array => ['visibility' => Visibility::INTERNAL]);
    }
}
