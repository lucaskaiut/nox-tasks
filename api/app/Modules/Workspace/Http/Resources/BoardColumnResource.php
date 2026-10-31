<?php

namespace App\Modules\Workspace\Http\Resources;

use App\Modules\Workspace\Models\BoardColumn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BoardColumn
 */
class BoardColumnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'color' => $this->color,
            'kind' => $this->kind?->value,
            'kind_label' => $this->kind?->label(),
            'position' => $this->position,
        ];
    }
}
