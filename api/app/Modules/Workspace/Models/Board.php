<?php

namespace App\Modules\Workspace\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Database\Factories\BoardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Quadro Kanban geral do tenant — existe um único por tenant e todas as
 * tarefas, de qualquer projeto, ficam em suas colunas.
 */
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

    protected static function newFactory(): BoardFactory
    {
        return BoardFactory::new();
    }
}
