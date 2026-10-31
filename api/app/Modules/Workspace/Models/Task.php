<?php

namespace App\Modules\Workspace\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Enums\TaskPriority;
use App\Modules\Workspace\Enums\Visibility;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'priority',
        'visibility',
        'starts_at',
        'due_at',
        'estimated_hours',
    ];

    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'visibility' => Visibility::class,
            'starts_at' => 'date',
            'due_at' => 'date',
            'estimated_hours' => 'decimal:2',
            'completed_at' => 'datetime',
            'position' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class, 'board_column_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_user');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'task_tag')->orderBy('name');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Subtask::class)->orderBy('position')->orderBy('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function isShared(): bool
    {
        return $this->visibility === Visibility::SHARED;
    }

    /**
     * Escopo de leitura. Usuários de cliente só enxergam tarefas
     * compartilhadas de projetos do próprio cliente.
     *
     * @param  Builder<Task>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isClientUser()) {
            return;
        }

        $query
            ->where($this->qualifyColumn('visibility'), Visibility::SHARED->value)
            ->whereIn(
                $this->qualifyColumn('project_id'),
                Project::query()->select('id')->where('client_id', $user->client_id),
            );
    }

    protected static function newFactory(): TaskFactory
    {
        return TaskFactory::new();
    }
}
