<?php

namespace App\Modules\User\Models;

use App\Modules\ACL\Models\Concerns\HasRoles;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\MasterTenantAccessService;
use App\Modules\Workspace\Models\Client;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use BelongsToTenant;
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use HasUuid;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'document',
        'password',
        'is_master',
    ];

    /**
     * client_id não é atribuível em massa: o vínculo com um cliente define o
     * escopo de dados do usuário e só é alterado por ClientService.
     */
    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_master' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /**
     * Usuário vinculado a um cliente: acesso restrito aos dados desse cliente,
     * independentemente das permissões concedidas pelos papéis.
     */
    public function isClientUser(): bool
    {
        return $this->client_id !== null;
    }

    public function canAccessTenant(Tenant|int $tenant): bool
    {
        return app(MasterTenantAccessService::class)->canAccess($this, $tenant);
    }

    /**
     * @return Collection<int, Tenant>
     */
    public function availableTenants(): Collection
    {
        return app(MasterTenantAccessService::class)->availableTenants($this);
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
