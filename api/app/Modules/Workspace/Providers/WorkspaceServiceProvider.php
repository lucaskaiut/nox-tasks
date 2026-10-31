<?php

namespace App\Modules\Workspace\Providers;

use App\Modules\Workspace\Models\Board;
use App\Modules\Workspace\Models\Client;
use App\Modules\Workspace\Models\Project;
use App\Modules\Workspace\Models\Task;
use App\Modules\Workspace\Models\TaskAttachment;
use App\Modules\Workspace\Policies\BoardPolicy;
use App\Modules\Workspace\Policies\ClientPolicy;
use App\Modules\Workspace\Policies\ProjectPolicy;
use App\Modules\Workspace\Policies\TaskAttachmentPolicy;
use App\Modules\Workspace\Policies\TaskPolicy;
use App\Modules\Workspace\Support\OperationContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class WorkspaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(OperationContext::class);
    }

    public function boot(): void
    {
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Board::class, BoardPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(TaskAttachment::class, TaskAttachmentPolicy::class);
    }
}
