<?php

namespace App\Modules\Workspace\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Workspace\Http\Resources\ActivityResource;
use App\Modules\Workspace\Http\Resources\MemberResource;
use App\Modules\Workspace\Http\Resources\TaskResource;
use App\Modules\Workspace\Models\Tag;
use App\Modules\Workspace\Services\DashboardService;
use App\Modules\Workspace\Services\MemberService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends ApiController
{
    public function dashboard(Request $request, DashboardService $dashboard): JsonResponse
    {
        $summary = $dashboard->summary($request->user());

        return $this->success([
            ...$summary,
            'priority_tasks' => TaskResource::collection($summary['priority_tasks']),
            'upcoming_deadlines' => TaskResource::collection($summary['upcoming_deadlines']),
            'recent_activity' => ActivityResource::collection($summary['recent_activity']),
        ]);
    }

    /**
     * Profissionais disponíveis para responsáveis/participantes.
     */
    public function members(Request $request, MemberService $members): JsonResponse
    {
        return $this->paginated(MemberResource::collection($members->paginate(
            (int) $request->integer('per_page', 50),
            $request->string('search')->limit(100, '')->toString() ?: null,
        )));
    }

    /**
     * Tags em uso nas tarefas visíveis ao usuário (clientes não descobrem tags
     * de tarefas internas ou de outros clientes).
     */
    public function tags(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success(
            Tag::query()
                ->when($user->isClientUser(), fn (Builder $q) => $q->whereHas('tasks', fn (Builder $t) => $t->visibleTo($user)))
                ->orderBy('name')
                ->limit(200)
                ->get(['name', 'color']),
        );
    }
}
