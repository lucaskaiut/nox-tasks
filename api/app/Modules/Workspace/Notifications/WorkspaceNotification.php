<?php

namespace App\Modules\Workspace\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notificação genérica do Workspace (canal database).
 *
 * kind: task_assigned | task_created | task_commented | task_due_changed |
 *       task_completed | project_updated | project_member_added
 */
class WorkspaceNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $title,
        public readonly string $message,
        public readonly array $payload,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'message' => $this->message,
            ...$this->payload,
        ];
    }
}
