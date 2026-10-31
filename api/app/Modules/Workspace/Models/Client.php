<?php

namespace App\Modules\Workspace\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Enums\ClientStatus;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'document',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ClientStatus::class,
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Profissionais enxergam todos os clientes do tenant; usuários de
     * cliente enxergam apenas o próprio.
     *
     * @param  Builder<Client>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isClientUser()) {
            $query->whereKey($user->client_id);
        }
    }

    protected static function newFactory(): ClientFactory
    {
        return ClientFactory::new();
    }
}
