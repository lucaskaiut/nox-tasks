<?php

namespace App\Modules\Workspace\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Enums\Visibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAttachment extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'original_name',
        'visibility',
    ];

    protected $hidden = [
        'disk',
        'path',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => Visibility::class,
            'size' => 'integer',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * @param  Builder<TaskAttachment>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isClientUser()) {
            $query->where($this->qualifyColumn('visibility'), Visibility::SHARED->value);
        }
    }
}
