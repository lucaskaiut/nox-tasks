<?php

use App\Modules\ACL\Http\Controllers\RoleController;
use App\Modules\ApiToken\Http\Controllers\ApiTokenController;
use App\Modules\Assistant\Http\Controllers\ChatController;
use App\Modules\Assistant\Http\Controllers\ConversationController;
use App\Modules\Audit\Http\Controllers\AuditLogController;
use App\Modules\Auth\Http\Controllers\AuthController;
use App\Modules\Billing\Http\Controllers\InvoiceController;
use App\Modules\Billing\Http\Controllers\PaymentMethodController;
use App\Modules\Billing\Http\Controllers\PlanController;
use App\Modules\Billing\Http\Controllers\SubscriptionController;
use App\Modules\Mcp\Http\Controllers\McpTokenController;
use App\Modules\Shared\Http\Controllers\FileUploadController;
use App\Modules\Tenant\Http\Controllers\TenantController;
use App\Modules\User\Http\Controllers\UserController;
use App\Modules\Webhook\Http\Controllers\WebhookController;
use App\Modules\Workspace\Http\Controllers\AttachmentController;
use App\Modules\Workspace\Http\Controllers\BoardController;
use App\Modules\Workspace\Http\Controllers\ClientController;
use App\Modules\Workspace\Http\Controllers\CommentController;
use App\Modules\Workspace\Http\Controllers\NotificationController;
use App\Modules\Workspace\Http\Controllers\ProjectController;
use App\Modules\Workspace\Http\Controllers\SubtaskController;
use App\Modules\Workspace\Http\Controllers\TaskController;
use App\Modules\Workspace\Http\Controllers\WorkspaceController;
use App\Modules\Workspace\Models\Board;
use App\Modules\Workspace\Models\Client;
use App\Modules\Workspace\Models\Project;
use App\Modules\Workspace\Models\Task;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');

    Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('select-tenant', [AuthController::class, 'selectTenant']);
    });
});

Route::get('billing/plans/catalog', [PlanController::class, 'catalog']);
Route::get('plans/public', [PlanController::class, 'catalog']);
Route::get('billing/gateways', [SubscriptionController::class, 'gateways']);
Route::get('payment-methods', [PaymentMethodController::class, 'index']);

/*
 * Pagamento e regularização ficam acessíveis mesmo com assinatura PAST_DUE/SUSPENDED.
 */
Route::middleware(['auth.multi:sanctum', 'tenant'])->group(function (): void {
    Route::middleware('tenant.child')->group(function (): void {
        Route::get('billing/subscription', [SubscriptionController::class, 'show'])->middleware('permission:subscription.read');
        Route::get('billing/invoices', [InvoiceController::class, 'index'])->middleware('permission:invoice.read');
        Route::get('billing/invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:invoice.read');
        Route::post('billing/invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->middleware('permission:invoice.read');
    });

    Route::get('billing/plans', [PlanController::class, 'index'])->middleware('permission:plan.read');
    Route::post('billing/plans', [PlanController::class, 'store'])->middleware('permission:plan.create');
    Route::get('billing/plans/{plan}', [PlanController::class, 'show'])->middleware('permission:plan.read');
    Route::match(['put', 'patch'], 'billing/plans/{plan}', [PlanController::class, 'update'])->middleware('permission:plan.update');
    Route::delete('billing/plans/{plan}', [PlanController::class, 'destroy'])->middleware('permission:plan.delete');

    Route::middleware('tenant.child')->group(function (): void {
        Route::post('billing/subscription', [SubscriptionController::class, 'store'])->middleware('permission:subscription.update');
        Route::post('billing/subscription/change-plan', [SubscriptionController::class, 'changePlan'])->middleware('permission:subscription.update');
        Route::post('billing/subscription/cancel', [SubscriptionController::class, 'cancel'])->middleware('permission:subscription.update');
        Route::post('billing/subscription/reactivate', [SubscriptionController::class, 'reactivate'])->middleware('permission:subscription.update');
    });
});

Route::middleware(['auth.multi:sanctum', 'tenant', 'subscription.active'])->group(function (): void {
    Route::get('tenant', [TenantController::class, 'show'])->middleware('permission:tenant.read');
    Route::match(['put', 'patch'], 'tenant', [TenantController::class, 'update'])->middleware('permission:tenant.update');

    Route::get('tenant/children', [TenantController::class, 'index'])->middleware('permission:tenant.read');
    Route::post('tenant/children', [TenantController::class, 'store'])->middleware('permission:tenant.create');

    Route::get('users', [UserController::class, 'index'])->middleware('permission:user.read');
    Route::post('users', [UserController::class, 'store'])->middleware('permission:user.create');
    Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:user.read');
    Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])->middleware('permission:user.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:user.delete');

    Route::get('roles', [RoleController::class, 'index'])->middleware('permission:role.read');
    Route::post('roles', [RoleController::class, 'store'])->middleware('permission:role.create');
    Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:role.read');
    Route::match(['put', 'patch'], 'roles/{role}', [RoleController::class, 'update'])->middleware('permission:role.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:role.delete');

    Route::get('api-tokens', [ApiTokenController::class, 'index'])->middleware('permission:api-token.read');
    Route::post('api-tokens', [ApiTokenController::class, 'store'])->middleware('permission:api-token.create');
    Route::match(['put', 'patch'], 'api-tokens/{apiToken}', [ApiTokenController::class, 'update'])->middleware('permission:api-token.update');
    Route::delete('api-tokens/{apiToken}', [ApiTokenController::class, 'destroy'])->middleware('permission:api-token.delete');

    Route::get('webhooks', [WebhookController::class, 'index'])->middleware('permission:webhook.read');
    Route::get('webhooks/events', [WebhookController::class, 'events'])->middleware('permission:webhook.read');
    Route::post('webhooks', [WebhookController::class, 'store'])->middleware('permission:webhook.create');
    Route::get('webhooks/{webhook}', [WebhookController::class, 'show'])->middleware('permission:webhook.read');
    Route::match(['put', 'patch'], 'webhooks/{webhook}', [WebhookController::class, 'update'])->middleware('permission:webhook.update');
    Route::delete('webhooks/{webhook}', [WebhookController::class, 'destroy'])->middleware('permission:webhook.delete');
    Route::get('webhooks/{webhook}/logs', [WebhookController::class, 'logs'])->middleware('permission:webhook.read');

    Route::get('audit', [AuditLogController::class, 'index'])->middleware('permission:audit.view');

    Route::post('uploads', FileUploadController::class);

    // Assistente de IA
    Route::get('assistant/conversations', [ConversationController::class, 'index'])->middleware('permission:assistant.view');
    Route::post('assistant/conversations', [ConversationController::class, 'store'])->middleware('permission:assistant.view');
    Route::get('assistant/conversations/{conversation}', [ConversationController::class, 'show'])->middleware('permission:assistant.view');
    Route::match(['put', 'patch'], 'assistant/conversations/{conversation}', [ConversationController::class, 'update'])->middleware('permission:assistant.view');
    Route::delete('assistant/conversations/{conversation}', [ConversationController::class, 'destroy'])->middleware('permission:assistant.view');
    Route::post('assistant/conversations/{conversation}/messages', [ChatController::class, 'send'])->middleware('permission:assistant.view');
});

/*
 * Workspace — clientes, projetos, tarefas e Kanban.
 *
 * Exige usuário real (auth:sanctum): tokens api_* de tenant não têm identidade
 * de usuário e não acessam este domínio. A autorização acontece no middleware
 * "can" (Policies) ANTES da validação, para que recursos fora do escopo
 * respondam 404 sem revelar nada.
 */
Route::middleware(['auth:sanctum', 'tenant', 'subscription.active'])->group(function (): void {
    Route::get('workspace/dashboard', [WorkspaceController::class, 'dashboard'])->can('viewAny', Project::class);
    Route::get('workspace/members', [WorkspaceController::class, 'members'])->can('assignOnCreate', Task::class);
    Route::get('workspace/tags', [WorkspaceController::class, 'tags'])->can('viewAny', Task::class);

    // Conexões de IA (tokens MCP do próprio usuário)
    Route::get('mcp/tokens', [McpTokenController::class, 'index'])->middleware('permission:mcp.use');
    Route::post('mcp/tokens', [McpTokenController::class, 'store'])->middleware('permission:mcp.use');
    Route::delete('mcp/tokens/{token}', [McpTokenController::class, 'destroy'])->middleware('permission:mcp.use')->whereNumber('token');

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->whereUuid('notification');

    Route::get('clients', [ClientController::class, 'index'])->can('viewAny', Client::class);
    Route::post('clients', [ClientController::class, 'store'])->can('create', Client::class);
    Route::get('clients/{client}', [ClientController::class, 'show'])->can('view', 'client');
    Route::match(['put', 'patch'], 'clients/{client}', [ClientController::class, 'update'])->can('update', 'client');
    Route::delete('clients/{client}', [ClientController::class, 'destroy'])->can('delete', 'client');
    Route::post('clients/{client}/users', [ClientController::class, 'storeUser'])->can('manageUsers', 'client');
    Route::delete('clients/{client}/users/{user}', [ClientController::class, 'destroyUser'])->can('manageUsers', 'client')->scopeBindings();

    Route::get('projects', [ProjectController::class, 'index'])->can('viewAny', Project::class);
    Route::post('projects', [ProjectController::class, 'store'])->can('create', Project::class);
    Route::get('projects/{project}', [ProjectController::class, 'show'])->can('view', 'project');
    Route::match(['put', 'patch'], 'projects/{project}', [ProjectController::class, 'update'])->can('update', 'project');
    Route::post('projects/{project}/archive', [ProjectController::class, 'archive'])->can('archive', 'project');
    Route::post('projects/{project}/restore', [ProjectController::class, 'restore'])->can('archive', 'project');
    Route::get('projects/{project}/board', [ProjectController::class, 'board'])->can('view', 'project');
    Route::get('projects/{project}/activity', [ProjectController::class, 'activity'])->can('view', 'project');
    Route::post('projects/{project}/tasks', [TaskController::class, 'store'])->can('create', [Task::class, 'project']);

    Route::get('boards', [BoardController::class, 'index'])->can('viewAny', Board::class);
    Route::post('boards', [BoardController::class, 'store'])->can('create', Board::class);
    Route::get('boards/{board}', [BoardController::class, 'show'])->can('view', 'board');
    Route::match(['put', 'patch'], 'boards/{board}', [BoardController::class, 'update'])->can('manage', 'board');
    Route::delete('boards/{board}', [BoardController::class, 'destroy'])->can('manage', 'board');
    Route::post('boards/{board}/columns', [BoardController::class, 'storeColumn'])->can('manage', 'board');
    Route::scopeBindings()->group(function (): void {
        Route::match(['put', 'patch'], 'boards/{board}/columns/{column}', [BoardController::class, 'updateColumn'])->can('manage', 'board');
        Route::post('boards/{board}/columns/{column}/move', [BoardController::class, 'moveColumn'])->can('manage', 'board');
        Route::delete('boards/{board}/columns/{column}', [BoardController::class, 'destroyColumn'])->can('manage', 'board');
    });

    Route::get('tasks', [TaskController::class, 'index'])->can('viewAny', Task::class);
    Route::get('tasks/{task}', [TaskController::class, 'show'])->can('view', 'task');
    Route::match(['put', 'patch'], 'tasks/{task}', [TaskController::class, 'update'])->can('update', 'task');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->can('delete', 'task');
    Route::post('tasks/{task}/assign', [TaskController::class, 'assign'])->can('assign', 'task');
    Route::post('tasks/{task}/move', [TaskController::class, 'move'])->can('move', 'task');
    Route::get('tasks/{task}/activity', [TaskController::class, 'activity'])->can('view', 'task');

    Route::get('tasks/{task}/subtasks', [SubtaskController::class, 'index'])->can('view', 'task');
    Route::post('tasks/{task}/subtasks', [SubtaskController::class, 'store'])->can('update', 'task');
    Route::scopeBindings()->group(function (): void {
        Route::match(['put', 'patch'], 'tasks/{task}/subtasks/{subtask}', [SubtaskController::class, 'update'])->can('update', 'task');
        Route::delete('tasks/{task}/subtasks/{subtask}', [SubtaskController::class, 'destroy'])->can('update', 'task');
    });

    Route::get('tasks/{task}/comments', [CommentController::class, 'index'])->can('view', 'task');
    Route::post('tasks/{task}/comments', [CommentController::class, 'store'])->can('comment', 'task');

    Route::get('tasks/{task}/attachments', [AttachmentController::class, 'index'])->can('view', 'task');
    Route::post('tasks/{task}/attachments', [AttachmentController::class, 'store'])->can('comment', 'task');
    Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->can('view', 'attachment');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->can('delete', 'attachment');
});
