<?php

namespace App\Modules\Workspace\Models;

use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Enums\OperationSource;
use App\Modules\Workspace\Enums\Visibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Histórico de alterações do domínio (quem, quando, o quê, antes/depois
 * e origem — interface, API ou MCP).
 */
class Activity extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'project_id',
        'task_id',
        'subject_type',
        'subject_id',
        'event',
        'changes',
        'visibility',
        'source',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'visibility' => Visibility::class,
            'source' => OperationSource::class,
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class)->withTrashed();
    }

    /**
     * Usuários de cliente só veem atividades compartilhadas dos próprios
     * projetos (e, quando ligadas a uma tarefa, apenas de tarefas visíveis).
     *
     * @param  Builder<Activity>  $query
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
            )
            ->where(function (Builder $q) use ($user): void {
                $q->whereNull($this->qualifyColumn('task_id'))
                    ->orWhereIn(
                        $this->qualifyColumn('task_id'),
                        Task::query()->select('id')->visibleTo($user),
                    );
            });
    }
}
