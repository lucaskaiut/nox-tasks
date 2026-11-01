<?php

namespace Tests\Feature\Mcp;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Mcp\Prompts\ProjectProgressPrompt;
use App\Modules\Mcp\Resources\ProjectContextResource;
use App\Modules\Mcp\Resources\TaskSummaryResource;
use App\Modules\Mcp\Servers\WorkspaceServer;
use App\Modules\Mcp\Tools\Boards\CreateBoardColumn;
use App\Modules\Mcp\Tools\Boards\GetBoard;
use App\Modules\Mcp\Tools\Clients\ListClients;
use App\Modules\Mcp\Tools\Comments\CreateTaskComment;
use App\Modules\Mcp\Tools\Comments\ListTaskActivity;
use App\Modules\Mcp\Tools\Comments\ListTaskComments;
use App\Modules\Mcp\Tools\Projects\CreateProject;
use App\Modules\Mcp\Tools\Projects\GetProject;
use App\Modules\Mcp\Tools\Projects\ListProjects;
use App\Modules\Mcp\Tools\Subtasks\CreateSubtask;
use App\Modules\Mcp\Tools\Subtasks\ListSubtasks;
use App\Modules\Mcp\Tools\Tasks\AssignTask;
use App\Modules\Mcp\Tools\Tasks\CreateTask;
use App\Modules\Mcp\Tools\Tasks\DeleteTask;
use App\Modules\Mcp\Tools\Tasks\GetTask;
use App\Modules\Mcp\Tools\Tasks\ListTasks;
use App\Modules\Mcp\Tools\Tasks\MoveTask;
use App\Modules\Mcp\Tools\Tasks\UpdateTask;
use App\Modules\Mcp\Tools\Workspace\GetDashboard;
use App\Modules\Mcp\Tools\Workspace\ListMembers;
use App\Modules\Mcp\Tools\Workspace\WhoAmI;
use App\Modules\Tenant\Support\CurrentTenant;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Enums\Visibility;
use App\Modules\Workspace\Models\Activity;
use App\Modules\Workspace\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Server\Testing\PendingTestResponse;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithWorkspace;
use Tests\TestCase;

class McpWorkspaceTest extends TestCase
{
    use InteractsWithWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorkspace();
    }

    /**
     * Simula o middleware "tenant" do endpoint HTTP: o tenant vem do usuário.
     */
    private function as(User $user): PendingTestResponse
    {
        app(CurrentTenant::class)->set($user->tenant);

        return WorkspaceServer::actingAs($user);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(TestResponse $response): array
    {
        return (fn () => $this->structuredContent())->call($response) ?? [];
    }

    // --- Autenticação e identidade -------------------------------------------------

    public function test_whoami_identifies_the_authenticated_user_and_ignores_identity_arguments(): void
    {
        $response = $this->as($this->clientUserA)->tool(WhoAmI::class, [
            'user_id' => $this->professional->uuid,
            'client_id' => $this->clientB->uuid,
        ])->assertOk();

        $data = $this->data($response);
        $this->assertSame($this->clientUserA->uuid, $data['id']);
        $this->assertSame('client', $data['profile']);
        $this->assertSame($this->clientA->uuid, $data['client']['id']);
    }

    public function test_unauthenticated_tool_call_fails(): void
    {
        app(CurrentTenant::class)->set($this->tenant);

        // Sem usuário autenticado, nenhuma tool é sequer registrada.
        WorkspaceServer::tool(ListTasks::class)->assertNotRegistered();
        WorkspaceServer::tools()->assertNotRegistered([ListTasks::class, WhoAmI::class]);
    }

    public function test_tool_fails_closed_without_tenant_context(): void
    {
        WorkspaceServer::actingAs($this->professional)->tool(ListTasks::class)
            ->assertHasErrors(['Contexto de tenant não resolvido.']);
    }

    // --- Disponibilidade das tools por perfil ---------------------------------------

    public function test_tool_catalog_follows_profile_and_permissions(): void
    {
        $admin = [ListClients::class, CreateProject::class, UpdateTask::class, DeleteTask::class, MoveTask::class,
            AssignTask::class, CreateBoardColumn::class, CreateSubtask::class, ListMembers::class];
        $shared = [WhoAmI::class, ListProjects::class, GetProject::class, ListTasks::class, GetTask::class, CreateTask::class,
            ListSubtasks::class, ListTaskComments::class, CreateTaskComment::class, ListTaskActivity::class, GetBoard::class, GetDashboard::class];

        $this->as($this->professional)->tools()->assertRegistered([...$admin, ...$shared]);

        $this->as($this->clientUserA)->tools()
            ->assertRegistered($shared)
            ->assertNotRegistered($admin);
    }

    public function test_client_cannot_call_hidden_administrative_tools(): void
    {
        $this->as($this->clientUserA)->tool(UpdateTask::class, ['task_id' => $this->sharedTaskA->uuid, 'title' => 'X'])
            ->assertNotRegistered();
        $this->as($this->clientUserA)->tool(DeleteTask::class, ['task_id' => $this->sharedTaskA->uuid])
            ->assertNotRegistered();

        $this->assertDatabaseHas('tasks', ['id' => $this->sharedTaskA->getKey(), 'deleted_at' => null]);
    }

    // --- Isolamento entre clientes (mesmos cenários da API) -------------------------

    public function test_professional_lists_all_projects_and_tasks(): void
    {
        $projects = collect($this->data($this->as($this->professional)->tool(ListProjects::class)->assertOk())['data'])->pluck('id');
        $this->assertEqualsCanonicalizing([$this->projectA->uuid, $this->projectB->uuid], $projects->all());

        $tasks = $this->data($this->as($this->professional)->tool(ListTasks::class));
        $this->assertSame(3, $tasks['meta']['total']);
    }

    public function test_client_lists_only_own_projects_and_shared_tasks(): void
    {
        $projects = collect($this->data($this->as($this->clientUserA)->tool(ListProjects::class))['data'])->pluck('id');
        $this->assertSame([$this->projectA->uuid], $projects->all());

        $tasks = collect($this->data($this->as($this->clientUserA)->tool(ListTasks::class))['data'])->pluck('id');
        $this->assertSame([$this->sharedTaskA->uuid], $tasks->all());

        // Filtros enviados pelo agente não ampliam o escopo.
        $this->assertSame(0, $this->data($this->as($this->clientUserA)->tool(ListTasks::class, [
            'project' => $this->projectB->uuid,
        ]))['meta']['total']);
        $this->assertSame(0, $this->data($this->as($this->clientUserA)->tool(ListTasks::class, [
            'visibility' => 'internal',
        ]))['meta']['total']);
    }

    public function test_client_cannot_get_foreign_or_internal_resources_by_id(): void
    {
        foreach ([$this->sharedTaskB, $this->internalTaskA] as $task) {
            $this->as($this->clientUserA)->tool(GetTask::class, ['task_id' => $task->uuid])
                ->assertHasErrors(['Recurso não encontrado.'])
                ->assertDontSee($task->title);
        }

        // ID inexistente e ID de outro cliente: mesma resposta.
        $this->as($this->clientUserA)->tool(GetTask::class, ['task_id' => '00000000-0000-4000-8000-000000000000'])
            ->assertHasErrors(['Recurso não encontrado.']);

        $this->as($this->clientUserA)->tool(GetProject::class, ['project_id' => $this->projectB->uuid])
            ->assertHasErrors(['Recurso não encontrado.'])
            ->assertDontSee('Projeto B');

        $this->as($this->clientUserA)->tool(GetBoard::class, ['project_id' => $this->projectB->uuid])
            ->assertHasErrors(['Recurso não encontrado.']);
    }

    public function test_client_cannot_list_subtasks_of_inaccessible_tasks(): void
    {
        $this->addSubtask($this->sharedTaskB, 'Subtarefa secreta B');

        $this->as($this->clientUserA)->tool(ListSubtasks::class, ['task_id' => $this->sharedTaskB->uuid])
            ->assertHasErrors(['Recurso não encontrado.'])
            ->assertDontSee('Subtarefa secreta B');
    }

    public function test_client_never_sees_internal_comments(): void
    {
        $this->addComment($this->sharedTaskA, $this->professional, Visibility::INTERNAL, 'Margem de 12% neste item');
        $this->addComment($this->sharedTaskA, $this->professional, Visibility::SHARED, 'Enviamos a nova versão');

        $this->as($this->clientUserA)->tool(ListTaskComments::class, ['task_id' => $this->sharedTaskA->uuid])
            ->assertOk()
            ->assertSee('Enviamos a nova versão')
            ->assertDontSee('Margem de 12%');

        $this->as($this->clientUserA)->resource(TaskSummaryResource::class, ['task_id' => $this->sharedTaskA->uuid])
            ->assertSee('Enviamos a nova versão')
            ->assertDontSee('Margem de 12%');
    }

    public function test_client_cannot_create_tasks_in_other_client_projects(): void
    {
        $this->as($this->clientUserA)->tool(CreateTask::class, ['project_id' => $this->projectB->uuid, 'title' => 'Intrusa'])
            ->assertHasErrors(['Recurso não encontrado.']);
        $this->assertDatabaseMissing('tasks', ['title' => 'Intrusa']);

        $created = $this->data($this->as($this->clientUserA)->tool(CreateTask::class, [
            'project_id' => $this->projectA->uuid,
            'title' => 'Pedido via agente',
            'visibility' => 'internal',
        ])->assertOk());
        $this->assertSame('shared', $created['visibility']);

        $this->as($this->clientUserA)->tool(CreateTask::class, [
            'project_id' => $this->projectA->uuid,
            'title' => 'Com responsável',
            'assignee_id' => $this->professional->uuid,
        ])->assertHasErrors(['Ação não autorizada para o seu perfil.']);
    }

    public function test_client_cannot_comment_on_foreign_tasks_and_comments_are_shared(): void
    {
        $this->as($this->clientUserA)->tool(CreateTaskComment::class, ['task_id' => $this->sharedTaskB->uuid, 'body' => 'oi'])
            ->assertHasErrors(['Recurso não encontrado.']);

        $comment = $this->data($this->as($this->clientUserA)->tool(CreateTaskComment::class, [
            'task_id' => $this->sharedTaskA->uuid, 'body' => 'Dúvida', 'visibility' => 'internal',
        ])->assertOk());
        $this->assertSame('shared', $comment['visibility']);
        $this->assertSame('mcp', $comment['source']);
    }

    public function test_client_gets_privilege_bump_but_policies_still_block(): void
    {
        // Mesmo com permissões de gestão concedidas ao papel, o vínculo com cliente bloqueia.
        $this->roleFor($this->tenant, DefaultRole::CLIENT)->grantPermissions(Permission::TASK_UPDATE, Permission::CLIENT_READ);
        $user = $this->clientUserA->fresh();

        $this->as($user)->tools()->assertNotRegistered([UpdateTask::class, ListClients::class]);
    }

    public function test_resources_and_prompts_respect_scope(): void
    {
        $this->as($this->clientUserA)->resource(ProjectContextResource::class, ['project_id' => $this->projectB->uuid])
            ->assertHasErrors(['Recurso não encontrado.'])
            ->assertDontSee('Projeto B');

        $this->as($this->clientUserA)->resource(ProjectContextResource::class, ['project_id' => $this->projectA->uuid])
            ->assertOk()
            ->assertSee('Tarefa compartilhada A')
            ->assertDontSee('Tarefa interna A')
            ->assertDontSee('Nota interna');

        $this->as($this->clientUserA)->prompt(ProjectProgressPrompt::class, ['project_id' => $this->projectB->uuid])
            ->assertHasErrors(['Recurso não encontrado.']);

        // Profissional pedindo texto para o cliente: dados internos são removidos.
        $this->as($this->professional)->prompt(ProjectProgressPrompt::class, [
            'project_id' => $this->projectA->uuid, 'audience' => 'client',
        ])->assertOk()->assertSee('Tarefa compartilhada A')->assertDontSee('Tarefa interna A')->assertDontSee('Nota interna');
    }

    public function test_client_dashboard_is_scoped(): void
    {
        $data = $this->data($this->as($this->clientUserA)->tool(GetDashboard::class)->assertOk());

        $this->assertSame('client', $data['profile']);
        $this->assertSame(1, $data['metrics']['open_tasks']);
        $this->assertSame(1, $data['metrics']['active_projects']);
    }

    // --- Operações de escrita do profissional ---------------------------------------

    public function test_professional_manages_work_through_mcp_with_history_source(): void
    {
        $project = $this->data($this->as($this->professional)->tool(CreateProject::class, [
            'name' => 'Projeto via agente',
            'client_id' => $this->clientA->uuid,
            'member_ids' => [$this->professional->uuid],
        ])->assertOk());

        $task = $this->data($this->as($this->professional)->tool(CreateTask::class, [
            'project_id' => $project['id'],
            'title' => 'Escrever briefing',
            'priority' => 'high',
            'assignee_id' => $this->professional->uuid,
        ])->assertOk());

        $this->as($this->professional)->tool(CreateSubtask::class, ['task_id' => $task['id'], 'title' => 'Rascunho'])->assertOk();

        $board = $this->data($this->as($this->professional)->tool(GetBoard::class, ['project_id' => $project['id']])->assertOk());
        $this->assertSame([$task['id']], collect($board['columns'])->pluck('tasks')->flatten(1)->pluck('id')->all());
        $done = collect($board['columns'])->firstWhere('kind', 'done')['id'];
        $moved = $this->data($this->as($this->professional)->tool(MoveTask::class, ['task_id' => $task['id'], 'column_id' => $done])->assertOk());
        $this->assertSame('done', $moved['status']['kind']);

        $this->assertSame(0, Activity::query()->where('source', '!=', 'mcp')->where('project_id', Task::query()->where('uuid', $task['id'])->value('project_id'))->count());
        $this->assertTrue(Activity::query()->where('event', 'task.moved')->where('source', 'mcp')->exists());

        $history = $this->data($this->as($this->professional)->tool(ListTaskActivity::class, ['task_id' => $task['id']]));
        $this->assertSame('mcp', $history['data'][0]['source']);
        $this->assertSame($this->professional->uuid, $history['data'][0]['user']['id']);
    }

    public function test_destructive_tool_requires_specific_permission(): void
    {
        // Profissional (sem task.delete negado? possui) — remove; papel sem permissão não vê a tool.
        $this->as($this->professional)->tool(DeleteTask::class, ['task_id' => $this->internalTaskA->uuid])->assertOk();
        $this->assertSoftDeleted('tasks', ['id' => $this->internalTaskA->getKey()]);

        $this->roleFor($this->tenant, DefaultRole::PROFESSIONAL)->revokePermissions(Permission::TASK_DELETE);
        $this->as($this->professional->fresh())->tools()->assertNotRegistered([DeleteTask::class]);
    }

    public function test_argument_validation_returns_errors(): void
    {
        $this->as($this->professional)->tool(CreateTask::class, ['project_id' => $this->projectA->uuid])
            ->assertHasErrors();

        $this->as($this->professional)->tool(UpdateTask::class, ['task_id' => $this->sharedTaskA->uuid, 'priority' => 'altissima'])
            ->assertHasErrors();

        $this->as($this->professional)->tool(GetTask::class, ['task_id' => 'nao-e-uuid'])
            ->assertHasErrors(['Recurso não encontrado.']);

        $this->as($this->professional)->tool(MoveTask::class, [
            'task_id' => $this->sharedTaskA->uuid,
            'column_id' => '00000000-0000-4000-8000-000000000000',
        ])->assertHasErrors();

        $this->as($this->professional)->tool(AssignTask::class, [
            'task_id' => $this->sharedTaskA->uuid,
            'assignee_id' => $this->clientUserA->uuid,
        ])->assertHasErrors();
    }

    public function test_pagination_in_list_tools(): void
    {
        Task::factory()->forProject($this->projectB)->count(30)->create();

        $first = $this->data($this->as($this->professional)->tool(ListTasks::class, ['per_page' => 10]));
        $this->assertSame(33, $first['meta']['total']);
        $this->assertCount(10, $first['data']);
        $this->assertTrue($first['meta']['has_more']);

        $last = $this->data($this->as($this->professional)->tool(ListTasks::class, ['per_page' => 10, 'page' => 4]));
        $this->assertCount(3, $last['data']);
        $this->assertFalse($last['meta']['has_more']);

        $this->as($this->professional)->tool(ListTasks::class, ['per_page' => 500])->assertHasErrors();
    }

    public function test_tool_calls_are_audited(): void
    {
        $this->as($this->clientUserA)->tool(GetTask::class, ['task_id' => $this->sharedTaskB->uuid]);

        $log = AuditLog::query()->where('action', 'mcp_tool_called')->latest('id')->first();
        $this->assertSame($this->clientUserA->getKey(), $log->user_id);
        $this->assertSame('get_task', $log->entity_id);
        $this->assertSame('denied', $log->details['outcome']);
    }

    // --- Transporte HTTP: autenticação, escopo do token e rate limit ---------------

    private function rpc(string $method, array $params = [], ?string $token = null): \Illuminate\Testing\TestResponse
    {
        // Cada chamada HTTP é uma conexão nova: não reaproveita o usuário já resolvido.
        $this->app['auth']->forgetGuards();
        $headers = ['Accept' => 'application/json, text/event-stream'];

        if ($token !== null) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        return $this->postJson('/mcp/workspace', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params,
        ], $headers);
    }

    public function test_http_endpoint_requires_mcp_token(): void
    {
        $this->rpc('tools/list')->assertUnauthorized();

        // Token de login comum (ability "*") não abre conexão MCP.
        $loginToken = $this->professional->createToken('auth_token')->plainTextToken;
        $this->rpc('tools/list', token: $loginToken)->assertUnauthorized();

        $mcpToken = $this->professional->createToken('mcp:Claude', ['mcp'])->plainTextToken;
        $this->rpc('tools/call', ['name' => 'whoami', 'arguments' => []], $mcpToken)
            ->assertOk()
            ->assertJsonPath('result.structuredContent.id', $this->professional->uuid)
            ->assertJsonPath('result.structuredContent.organization', $this->tenant->name);
    }

    public function test_http_isolation_between_clients(): void
    {
        $token = $this->clientUserA->createToken('mcp:agente', ['mcp'])->plainTextToken;

        $this->rpc('tools/call', ['name' => 'get_task', 'arguments' => ['task_id' => $this->sharedTaskB->uuid]], $token)
            ->assertOk()
            ->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.content.0.text', 'Recurso não encontrado.');

        $this->rpc('tools/call', ['name' => 'update_task', 'arguments' => ['task_id' => $this->sharedTaskA->uuid, 'title' => 'x']], $token)
            ->assertJsonPath('error.code', -32602);
    }

    public function test_mcp_token_cannot_be_used_on_rest_api(): void
    {
        $token = $this->professional->createToken('mcp:agente', ['mcp'])->plainTextToken;

        $this->withToken($token)->getJson('/api/tasks')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/users')->assertForbidden();
    }

    public function test_user_without_mcp_permission_is_rejected(): void
    {
        $this->roleFor($this->tenant, DefaultRole::CLIENT)->revokePermissions(Permission::MCP_USE);
        $token = $this->clientUserA->createToken('mcp:agente', ['mcp'])->plainTextToken;

        $this->rpc('tools/list', token: $token)->assertForbidden();
    }

    public function test_http_rate_limit_per_user(): void
    {
        config(['services.mcp.rate_limit' => 3]);
        $token = $this->professional->createToken('mcp:agente', ['mcp'])->plainTextToken;

        foreach (range(1, 3) as $ignored) {
            $this->rpc('tools/list', token: $token)->assertOk();
        }

        $this->rpc('tools/list', token: $token)->assertStatus(429);
    }

    public function test_users_manage_their_own_mcp_tokens(): void
    {
        Sanctum::actingAs($this->clientUserA);

        $created = $this->postJson('/api/mcp/tokens', ['name' => 'Claude Desktop', 'expires_in_days' => 30])
            ->assertCreated()
            ->json('data');

        $this->assertStringContainsString('|', $created['token']);
        $this->getJson('/api/mcp/tokens')->assertJsonPath('data.tokens.0.name', 'Claude Desktop');

        // Outro usuário não revoga o token alheio.
        Sanctum::actingAs($this->clientUserB);
        $this->deleteJson("/api/mcp/tokens/{$created['id']}")->assertNotFound();

        Sanctum::actingAs($this->clientUserA);
        $this->deleteJson("/api/mcp/tokens/{$created['id']}")->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
