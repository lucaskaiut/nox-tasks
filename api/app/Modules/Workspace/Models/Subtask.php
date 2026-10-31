<?php

namespace App\Modules\Workspace\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Enums\SubtaskStatus;
use App\Modules\Workspace\Enums\TaskPriority;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Subtarefas não possuem regras de acesso próprias: herdam as da tarefa.
 */
class Subtask extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'title',
        'description',
        'status',
        'priority',
        'due_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubtaskStatus::class,
            'priority' => TaskPriority::class,
            'due_at' => 'date',
            'completed_at' => 'datetime',
            'position' => 'integer',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id')->withTrashed();
    }
}
