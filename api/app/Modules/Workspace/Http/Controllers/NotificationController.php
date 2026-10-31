<?php

namespace App\Modules\Workspace\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Central de notificações do usuário autenticado (somente as próprias e
 * apenas do tenant ativo).
 */
class NotificationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $page = $this->scoped($request)
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->latest()
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 50));

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => collect($page->items())->map(fn (DatabaseNotification $n) => $this->present($n))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'unread' => $this->scoped($request)->whereNull('read_at')->count(),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->success(['unread' => $this->scoped($request)->whereNull('read_at')->count()]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $this->scoped($request)->whereKey($notification)->firstOrFail()->markAsRead();

        return $this->success(null);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->scoped($request)->whereNull('read_at')->update(['read_at' => now()]);

        return $this->success(null);
    }

    /**
     * @return Builder<DatabaseNotification>
     */
    private function scoped(Request $request)
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', $request->user()->getMorphClass())
            ->where('notifiable_id', $request->user()->getKey())
            ->where('data->tenant_id', TenantContext::tenantId());
    }

    /**
     * @return array<string, mixed>
     */
    private function present(DatabaseNotification $notification): array
    {
        $data = $notification->data;
        unset($data['tenant_id']);

        return [
            'id' => $notification->getKey(),
            ...$data,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
