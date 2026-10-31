<?php

namespace App\Modules\Workspace\Http\Resources;

use App\Modules\Workspace\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $subtasks = $this->whenCounted('subtasks', fn () => (int) $this->subtasks_count, null);
        $doneSubtasks = (int) ($this->done_subtasks_count ?? 0);

        if ($subtasks === null && $this->relationLoaded('subtasks')) {
            $subtasks = $this->subtasks->count();
            $doneSubtasks = $this->subtasks->where('status.value', 'done')->count();
        }

        return [
            'id' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project?->uuid,
                'name' => $this->project?->name,
                'is_archived' => $this->project?->archived_at !== null,
            ]),
            'client' => $this->when(
                $this->relationLoaded('project') && $this->project?->relationLoaded('client'),
                fn () => ['id' => $this->project->client?->uuid, 'name' => $this->project->client?->name],
            ),
            'status' => BoardColumnResource::make($this->whenLoaded('column')),
            'priority' => $this->priority?->value,
            'priority_label' => $this->priority?->label(),
            'visibility' => $this->visibility?->value,
            'assignee' => MemberResource::make($this->whenLoaded('assignee')),
            'participants' => MemberResource::collection($this->whenLoaded('participants')),
            'creator' => MemberResource::make($this->whenLoaded('creator')),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag) => [
                'name' => $tag->name,
                'color' => $tag->color,
            ])->values()),
            'starts_at' => $this->starts_at?->toDateString(),
            'due_at' => $this->due_at?->toDateString(),
            'estimated_hours' => $this->estimated_hours !== null ? (float) $this->estimated_hours : null,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'is_overdue' => $this->isOverdue(),
            'position' => $this->position,
            'subtasks_summary' => $subtasks === null ? null : [
                'total' => $subtasks,
                'done' => $doneSubtasks,
                'percent' => $subtasks > 0 ? (int) round($doneSubtasks / $subtasks * 100) : 0,
            ],
            'subtasks' => SubtaskResource::collection($this->whenLoaded('subtasks')),
            'comments_count' => $this->whenCounted('comments'),
            'attachments_count' => $this->whenCounted('attachments'),
            'last_comment_at' => $this->when(
                $this->resource->hasAttribute('last_comment_at'),
                fn () => $this->last_comment_at ? Carbon::parse($this->last_comment_at)->toIso8601String() : null,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function isOverdue(): bool
    {
        if ($this->due_at === null || $this->completed_at !== null) {
            return false;
        }

        $kind = $this->relationLoaded('column') ? $this->column?->kind : null;

        return ! ($kind?->isClosed() ?? false) && $this->due_at->isBefore(today());
    }
}
