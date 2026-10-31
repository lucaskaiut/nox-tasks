<?php

namespace App\Modules\Workspace\Policies;

use App\Modules\User\Models\User;
use App\Modules\Workspace\Enums\Visibility;
use App\Modules\Workspace\Models\TaskAttachment;
use App\Modules\Workspace\Policies\Concerns\AuthorizesWorkspace;
use Illuminate\Auth\Access\Response;

class TaskAttachmentPolicy
{
    use AuthorizesWorkspace;

    public function __construct(private readonly TaskPolicy $tasks) {}

    public function view(User $user, TaskAttachment $attachment): Response
    {
        return $this->readable($this->visible($user, $attachment));
    }

    /**
     * Remoção: o próprio autor do upload ou um profissional que pode editar a tarefa.
     */
    public function delete(User $user, TaskAttachment $attachment): Response
    {
        $allowed = (int) $attachment->user_id === (int) $user->getKey()
            || $this->tasks->update($user, $attachment->task)->allowed();

        return $this->act($this->visible($user, $attachment), $allowed);
    }

    private function visible(User $user, TaskAttachment $attachment): bool
    {
        if (! $this->inTenant($attachment) || $attachment->task === null) {
            return false;
        }

        if (! $this->tasks->visible($user, $attachment->task)) {
            return false;
        }

        return ! $user->isClientUser() || $attachment->visibility === Visibility::SHARED;
    }
}
