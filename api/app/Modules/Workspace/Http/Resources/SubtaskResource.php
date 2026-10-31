<?php

namespace App\Modules\Workspace\Http\Resources;

use App\Modules\Workspace\Models\Subtask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subtask
 */
class SubtaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'priority' => $this->priority?->value,
            'due_at' => $this->due_at?->toDateString(),
            'position' => $this->position,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'assignee' => MemberResource::make($this->whenLoaded('assignee')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
