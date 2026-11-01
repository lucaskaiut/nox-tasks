<?php

namespace App\Modules\Workspace\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Enums\ProjectStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'status',
        'starts_at',
        'due_at',
        'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'starts_at' => 'date',
            'due_at' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Escopo de leitura: profissionais veem todos os projetos do tenant,
     * usuários de cliente apenas os projetos do próprio cliente.
     *
     * @param  Builder<Project>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isClientUser()) {
            $query->where($this->qualifyColumn('client_id'), $user->client_id);
        }
    }

    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }
}
