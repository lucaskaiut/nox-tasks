<?php

namespace App\Modules\Workspace\Http\Resources;

use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representação compacta de um usuário. E-mail e vínculo só são expostos a
 * profissionais — clientes não acessam dados internos de profissionais.
 *
 * @mixin User
 */
class MemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $isProfessionalViewer = $viewer instanceof User && ! $viewer->isClientUser();

        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'email' => $this->when($isProfessionalViewer && $this->resource->hasAttribute('email'), fn () => $this->email),
            'is_client' => $this->when($this->resource->hasAttribute('client_id'), fn () => $this->client_id !== null),
        ];
    }
}
