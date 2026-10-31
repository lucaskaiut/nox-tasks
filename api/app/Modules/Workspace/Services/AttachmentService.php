<?php

namespace App\Modules\Workspace\Services;

use App\Modules\User\Models\User;
use App\Modules\Workspace\Enums\Visibility;
use App\Modules\Workspace\Models\Task;
use App\Modules\Workspace\Models\TaskAttachment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Anexos ficam em disco privado; o download passa sempre pela Policy.
 */
class AttachmentService
{
    private const DISK = 'local';

    public function __construct(private readonly ActivityService $activity) {}

    /**
     * @return Collection<int, TaskAttachment>
     */
    public function list(User $actor, Task $task): Collection
    {
        return $task->attachments()->visibleTo($actor)->with('uploader:id,uuid,name')->latest()->get();
    }

    public function store(User $actor, Task $task, UploadedFile $file, ?string $visibility = null): TaskAttachment
    {
        $resolved = match (true) {
            $actor->isClientUser() => Visibility::SHARED,
            ! $task->isShared() => Visibility::INTERNAL,
            default => Visibility::tryFrom((string) $visibility) ?? Visibility::INTERNAL,
        };

        $path = $file->store("attachments/{$task->tenant_id}/{$task->uuid}", self::DISK);

        return DB::transaction(function () use ($actor, $task, $file, $path, $resolved): TaskAttachment {
            $attachment = new TaskAttachment([
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            ]);
            $attachment->visibility = $resolved;
            $attachment->disk = self::DISK;
            $attachment->path = (string) $path;
            $attachment->mime_type = $file->getMimeType();
            $attachment->size = (int) $file->getSize();
            $attachment->task()->associate($task);
            $attachment->uploader()->associate($actor);
            $attachment->save();

            $this->activity->record('attachment.created', $attachment, $resolved, task: $task, changes: [
                'file' => ['from' => null, 'to' => $attachment->original_name],
            ]);

            return $attachment->load('uploader:id,uuid,name');
        });
    }

    public function download(TaskAttachment $attachment): StreamedResponse
    {
        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function delete(TaskAttachment $attachment): void
    {
        DB::transaction(function () use ($attachment): void {
            $attachment->delete();
            Storage::disk($attachment->disk)->delete($attachment->path);

            $this->activity->record('attachment.deleted', $attachment, $attachment->visibility, task: $attachment->task, changes: [
                'file' => ['from' => $attachment->original_name, 'to' => null],
            ]);
        });
    }
}
