<?php

namespace App\Modules\Workspace\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use Database\Factories\BoardFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Board extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<BoardFactory> */
    use HasFactory;

    use HasUuid;

    protected $fillable = [
        'name',
        'description',
    ];

    public function columns(): HasMany
    {
        return $this->hasMany(BoardColumn::class)->orderBy('position')->orderBy('id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Clientes só enxergam quadros associados aos próprios projetos.
     *
     * @param  Builder<Board>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isClientUser()) {
            $query->whereHas('projects', fn (Builder $q) => $q->where('client_id', $user->client_id));
        }
    }

    protected static function newFactory(): BoardFactory
    {
        return BoardFactory::new();
    }
}
