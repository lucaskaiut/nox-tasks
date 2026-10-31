<?php

namespace App\Modules\Workspace\Http\Resources;

use App\Modules\Workspace\Models\TaskComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaskComment
 */
class TaskCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'body' => $this->body,
            'visibility' => $this->visibility?->value,
            'source' => $this->source?->value,
            'author' => MemberResource::make($this->whenLoaded('author')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
