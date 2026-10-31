<?php

namespace App\Modules\Workspace\Http\Resources;

use App\Modules\Workspace\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Activity
 */
class ActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'event' => $this->event,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'changes' => $this->changes,
            'source' => $this->source?->value,
            'visibility' => $this->visibility?->value,
            'user' => MemberResource::make($this->whenLoaded('user')),
            'project' => $this->whenLoaded('project', fn () => $this->project ? [
                'id' => $this->project->uuid,
                'name' => $this->project->name,
            ] : null),
            'task' => $this->whenLoaded('task', fn () => $this->task ? [
                'id' => $this->task->uuid,
                'title' => $this->task->title,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
