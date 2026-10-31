<?php

namespace App\Modules\Workspace\Http\Resources;

use App\Modules\User\Models\User;
use App\Modules\Workspace\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $isProfessional = $viewer instanceof User && ! $viewer->isClientUser();

        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client?->uuid,
                'name' => $this->client?->name,
            ]),
            'board' => $this->whenLoaded('board', fn () => [
                'id' => $this->board?->uuid,
                'name' => $this->board?->name,
                'columns' => $this->board?->relationLoaded('columns')
                    ? BoardColumnResource::collection($this->board->columns)
                    : null,
            ]),
            'members' => MemberResource::collection($this->whenLoaded('members')),
            'starts_at' => $this->starts_at?->toDateString(),
            'due_at' => $this->due_at?->toDateString(),
            'internal_notes' => $this->when($isProfessional, fn () => $this->internal_notes),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'is_archived' => $this->isArchived(),
            'progress' => $this->when(
                $this->resource->hasAttribute('tasks_count'),
                fn () => $this->progress(),
            ),
            'creator' => MemberResource::make($this->whenLoaded('creator')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{total: int, done: int, cancelled: int, overdue: int, open: int, percent: int}
     */
    private function progress(): array
    {
        $total = (int) $this->tasks_count;
        $done = (int) $this->done_tasks_count;
        $cancelled = (int) $this->cancelled_tasks_count;
        $considered = $total - $cancelled;

        return [
            'total' => $total,
            'done' => $done,
            'cancelled' => $cancelled,
            'overdue' => (int) $this->overdue_tasks_count,
            'open' => max(0, $considered - $done),
            'percent' => $considered > 0 ? (int) round($done / $considered * 100) : 0,
        ];
    }
}
