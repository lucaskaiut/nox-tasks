<?php

namespace Tests\Feature\Workspace;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\ApiToken\Services\ApiTokenService;
use App\Modules\Tenant\Support\CurrentTenant;
use App\Modules\Workspace\Enums\ColumnKind;
use App\Modules\Workspace\Enums\Visibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithWorkspace;
use Tests\TestCase;

/**
 * Isolamento de dados entre clientes e respeito ao ACL na API HTTP.
 * (Os mesmos cenários são verificados no MCP em McpIsolationTest.)
 */
class WorkspaceIsolationTest extends TestCase
{
    use InteractsWithWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorkspace();
    }

    // 1
    public function test_professional_lists_all_projects(): void
    {
        Sanctum::actingAs($this->professional);

        $ids = collect($this->getJson('/api/projects')->assertOk()->json('data'))->pluck('id');

        $this->assertEqualsCanonicalizing([$this->projectA->uuid, $this->projectB->uuid], $ids->all());
    }

    // 2
    public function test_professional_lists_all_tasks_including_internal(): void
    {
        Sanctum::actingAs($this->professional);

        $ids = collect($this->getJson('/api/tasks')->assertOk()->json('data'))->pluck('id');

        $this->assertEqualsCanonicalizing(
            [$this->sharedTaskA->uuid, $this->internalTaskA->uuid, $this->sharedTaskB->uuid],
            $ids->all(),
        );
    }

    // 3
    public function test_client_lists_only_own_projects_even_when_filtering_by_other_client(): void
    {
        Sanctum::actingAs($this->clientUserA);

        $ids = collect($this->getJson('/api/projects')->assertOk()->json('data'))->pluck('id');
        $this->assertSame([$this->projectA->uuid], $ids->all());

        // Filtro do usuário nunca amplia o escopo.
        $this->getJson('/api/projects?client='.$this->clientB->uuid)
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    // 4
    public function test_client_lists_only_shared_tasks_of_own_client(): void
    {
        Sanctum::actingAs($this->clientUserA);

        $ids = collect($this->getJson('/api/tasks')->assertOk()->json('data'))->pluck('id');
        $this->assertSame([$this->sharedTaskA->uuid], $ids->all());

        $this->getJson('/api/tasks?project='.$this->projectB->uuid)->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/tasks?visibility=internal')->assertOk()->assertJsonPath('meta.total', 0);
    }

    // 5
    public function test_client_cannot_get_task_of_other_client_or_internal_task_by_id(): void
    {
        Sanctum::actingAs($this->clientUserA);

        $this->getJson("/api/tasks/{$this->sharedTaskA->uuid}")->assertOk();

        foreach ([$this->sharedTaskB, $this->internalTaskA] as $task) {
            $this->getJson("/api/tasks/{$task->uuid}")
                ->assertNotFound()
                ->assertJsonPath('message', 'Recurso não encontrado.')
                ->assertJsonMissing(['title' => $task->title]);
        }
    }

    // 6
    public function test_client_cannot_get_project_of_other_client_by_id(): void
    {
        Sanctum::actingAs($this->clientUserA);

        $this->getJson("/api/projects/{$this->projectA->uuid}")
            ->assertOk()
            ->assertJsonMissingPath('data.internal_notes');

        $this->getJson("/api/projects/{$this->projectB->uuid}")->assertNotFound();
        $this->getJson("/api/projects/{$this->projectB->uuid}/board")->assertNotFound();
        $this->getJson("/api/projects/{$this->projectB->uuid}/activity")->assertNotFound();
    }

    // 7
    public function test_client_cannot_read_subtasks_of_inaccessible_tasks(): void
    {
        $foreign = $this->addSubtask($this->sharedTaskB, 'Subtarefa B');
        $internal = $this->addSubtask($this->internalTaskA, 'Subtarefa interna');

        Sanctum::actingAs($this->clientUserA);

        $this->getJson("/api/tasks/{$this->sharedTaskB->uuid}/subtasks")->assertNotFound();
        $this->getJson("/api/tasks/{$this->internalTaskA->uuid}/subtasks")->assertNotFound();

        // Subtarefa de B via tarefa de A: scoped binding impede o acesso cruzado.
        $this->patchJson("/api/tasks/{$this->sharedTaskA->uuid}/subtasks/{$foreign->uuid}", ['title' => 'x'])
            ->assertNotFound();
        Sanctum::actingAs($this->professional);
        $this->patchJson("/api/tasks/{$this->sharedTaskA->uuid}/subtasks/{$foreign->uuid}", ['title' => 'x'])
            ->assertNotFound();

        $this->assertDatabaseHas('subtasks', ['id' => $foreign->getKey(), 'title' => 'Subtarefa B']);
        $this->assertDatabaseHas('subtasks', ['id' => $internal->getKey(), 'title' => 'Subtarefa interna']);
    }

    // 8
    public function test_client_never_sees_internal_comments_or_activity(): void
    {
        $this->addComment($this->sharedTaskA, $this->professional, Visibility::INTERNAL, 'Comentário interno sigiloso');
        $this->addComment($this->sharedTaskA, $this->professional, Visibility::SHARED, 'Comentário para o cliente');

        Sanctum::actingAs($this->clientUserA);

        $bodies = collect($this->getJson("/api/tasks/{$this->sharedTaskA->uuid}/comments")->assertOk()->json('data'))
            ->pluck('body');

        $this->assertSame(['Comentário para o cliente'], $bodies->all());

        // Comentário criado por cliente é sempre compartilhado, mesmo pedindo "internal".
        $this->postJson("/api/tasks/{$this->sharedTaskA->uuid}/comments", [
            'body' => 'Pergunta do cliente',
            'visibility' => 'internal',
        ])->assertCreated()->assertJsonPath('data.visibility', 'shared');
    }

    // 9
    public function test_client_cannot_change_tasks(): void
    {
        Sanctum::actingAs($this->clientUserA);

        $this->patchJson("/api/tasks/{$this->sharedTaskB->uuid}", ['title' => 'Invadido'])->assertNotFound();
        $this->deleteJson("/api/tasks/{$this->sharedTaskB->uuid}")->assertNotFound();
        $this->postJson("/api/tasks/{$this->sharedTaskB->uuid}/move", [
            'column_id' => $this->columnOf($this->projectB, ColumnKind::DONE),
        ])->assertNotFound();
        $this->postJson("/api/tasks/{$this->sharedTaskB->uuid}/comments", ['body' => 'oi'])->assertNotFound();

        // Na própria tarefa, sem permissão de gestão: 403.
        $this->patchJson("/api/tasks/{$this->sharedTaskA->uuid}", ['title' => 'Alterado'])->assertForbidden();
        $this->postJson("/api/tasks/{$this->sharedTaskA->uuid}/move", [
            'column_id' => $this->columnOf($this->projectA, ColumnKind::DONE),
        ])->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $this->sharedTaskB->getKey(), 'title' => 'Tarefa compartilhada B']);
        $this->assertDatabaseHas('tasks', ['id' => $this->sharedTaskA->getKey(), 'title' => 'Tarefa compartilhada A']);
    }

    // 10
    public function test_client_cannot_create_tasks_in_other_client_projects(): void
    {
        Sanctum::actingAs($this->clientUserA);

        $this->postJson("/api/projects/{$this->projectB->uuid}/tasks", ['title' => 'Intrusa'])->assertNotFound();
        $this->assertDatabaseMissing('tasks', ['title' => 'Intrusa']);

        // No próprio projeto pode — sempre compartilhada e sem responsável.
        $this->postJson("/api/projects/{$this->projectA->uuid}/tasks", [
            'title' => 'Pedido do cliente',
            'visibility' => 'internal',
        ])
            ->assertCreated()
            ->assertJsonPath('data.visibility', 'shared')
            ->assertJsonPath('data.assignee', null);

        // Não pode atribuir profissionais.
        $this->postJson("/api/projects/{$this->projectA->uuid}/tasks", [
            'title' => 'Com responsável',
            'assignee_id' => $this->professional->uuid,
        ])->assertForbidden();
    }

    // 11
    public function test_client_cannot_manage_client_user_links(): void
    {
        Sanctum::actingAs($this->clientUserA);

        $this->postJson("/api/clients/{$this->clientA->uuid}/users", [
            'name' => 'Novo', 'email' => 'novo@x.com', 'password' => '12345678',
        ])->assertNotFound();
        $this->postJson("/api/clients/{$this->clientB->uuid}/users", [
            'name' => 'Novo', 'email' => 'novo@x.com', 'password' => '12345678',
        ])->assertNotFound();
        $this->deleteJson("/api/clients/{$this->clientB->uuid}/users/{$this->clientUserB->uuid}")->assertNotFound();

        // client_id nunca é aceito pela API de usuários.
        $this->putJson("/api/users/{$this->clientUserA->uuid}", ['client_id' => $this->clientB->getKey()])->assertForbidden();

        $this->assertSame($this->clientA->getKey(), $this->clientUserA->fresh()->client_id);
        $this->assertDatabaseMissing('users', ['email' => 'novo@x.com']);
    }

    // 12
    public function test_client_cannot_use_administrative_endpoints_even_with_permissions(): void
    {
        // Mesmo que um admin conceda permissões de gestão ao papel Cliente,
        // o vínculo com cliente mantém as ações administrativas bloqueadas.
        $this->roleFor($this->tenant, DefaultRole::CLIENT)->grantPermissions(
            Permission::CLIENT_READ, Permission::CLIENT_CREATE, Permission::PROJECT_CREATE,
            Permission::TASK_UPDATE, Permission::BOARD_MANAGE,
        );

        Sanctum::actingAs($this->clientUserA->fresh());

        $this->getJson('/api/clients')->assertForbidden();
        $this->postJson('/api/clients', ['name' => 'X', 'email' => 'x@x.com'])->assertForbidden();
        $this->postJson('/api/projects', ['name' => 'X', 'client_id' => $this->clientA->uuid])->assertForbidden();
        $this->patchJson('/api/board', ['name' => 'X'])->assertForbidden();
        $this->postJson('/api/board/columns', ['name' => 'X'])->assertForbidden();
        $this->patchJson("/api/tasks/{$this->sharedTaskA->uuid}", ['title' => 'X'])->assertForbidden();
        $this->getJson('/api/workspace/members')->assertForbidden();

        // Quadro geral: só as próprias tarefas compartilhadas.
        $ids = collect($this->getJson('/api/board')->assertOk()->json('data.tasks'))->pluck('id');
        $this->assertSame([$this->sharedTaskA->uuid], $ids->all());
        $this->getJson("/api/board?project={$this->projectB->uuid}")->assertOk()->assertJsonCount(0, 'data.tasks');
    }

    // 13
    public function test_guest_cannot_access_workspace(): void
    {
        $this->getJson('/api/projects')->assertUnauthorized();
        $this->getJson('/api/tasks')->assertUnauthorized();
        $this->getJson("/api/tasks/{$this->sharedTaskA->uuid}")->assertUnauthorized();
        $this->getJson('/api/workspace/dashboard')->assertUnauthorized();
    }

    public function test_tenant_api_tokens_cannot_access_workspace(): void
    {
        app(CurrentTenant::class)->set($this->tenant);
        $plain = app(ApiTokenService::class)->issue('integração')->plainTextToken;
        $this->forgetTenantContext();

        $this->withToken($plain)->getJson('/api/tasks')->assertUnauthorized();
    }

    public function test_other_tenant_cannot_see_anything(): void
    {
        $otherTenant = $this->createTenantWithRoles(['domain' => 'outro.com.br']);
        $outsider = $this->createAdmin($otherTenant);

        Sanctum::actingAs($outsider);

        $this->getJson('/api/projects')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/tasks')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson("/api/tasks/{$this->sharedTaskA->uuid}")->assertNotFound();
        $this->getJson("/api/clients/{$this->clientA->uuid}")->assertNotFound();
        $this->getJson('/api/board')->assertOk()->assertJsonCount(0, 'data.tasks');
    }

    public function test_client_dashboard_counts_only_own_shared_tasks(): void
    {
        Sanctum::actingAs($this->clientUserA);

        $this->getJson('/api/workspace/dashboard')
            ->assertOk()
            ->assertJsonPath('data.profile', 'client')
            ->assertJsonPath('data.metrics.active_projects', 1)
            ->assertJsonPath('data.metrics.open_tasks', 1);

        Sanctum::actingAs($this->professional);

        $this->getJson('/api/workspace/dashboard')
            ->assertOk()
            ->assertJsonPath('data.profile', 'professional')
            ->assertJsonPath('data.metrics.active_projects', 2)
            ->assertJsonPath('data.metrics.open_tasks', 3);
    }

    public function test_client_cannot_download_internal_or_foreign_attachments(): void
    {
        Storage::fake('local');

        Sanctum::actingAs($this->professional);
        $internalFile = $this->postJson("/api/tasks/{$this->sharedTaskA->uuid}/attachments", [
            'file' => UploadedFile::fake()->create('custos.pdf', 10, 'application/pdf'),
            'visibility' => 'internal',
        ])->assertCreated()->json('data.id');
        $sharedFile = $this->postJson("/api/tasks/{$this->sharedTaskA->uuid}/attachments", [
            'file' => UploadedFile::fake()->create('layout.pdf', 10, 'application/pdf'),
            'visibility' => 'shared',
        ])->assertCreated()->json('data.id');
        $foreignFile = $this->postJson("/api/tasks/{$this->sharedTaskB->uuid}/attachments", [
            'file' => UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'),
            'visibility' => 'shared',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($this->clientUserA);

        $this->get("/api/attachments/{$sharedFile}/download")->assertOk();
        $this->getJson("/api/attachments/{$internalFile}/download")->assertNotFound();
        $this->getJson("/api/attachments/{$foreignFile}/download")->assertNotFound();

        $names = collect($this->getJson("/api/tasks/{$this->sharedTaskA->uuid}/attachments")->json('data'))->pluck('name');
        $this->assertSame(['layout.pdf'], $names->all());
    }

    public function test_client_does_not_receive_professional_emails(): void
    {
        $this->sharedTaskA->forceFill(['assignee_id' => $this->professional->getKey()])->save();

        Sanctum::actingAs($this->clientUserA);

        $this->getJson("/api/tasks/{$this->sharedTaskA->uuid}")
            ->assertOk()
            ->assertJsonPath('data.assignee.name', $this->professional->name)
            ->assertJsonMissingPath('data.assignee.email');
    }

    public function test_client_only_discovers_tags_of_visible_tasks(): void
    {
        Sanctum::actingAs($this->professional);
        $this->patchJson("/api/tasks/{$this->sharedTaskA->uuid}", ['tags' => ['layout']])->assertOk();
        $this->patchJson("/api/tasks/{$this->internalTaskA->uuid}", ['tags' => ['margem-baixa']])->assertOk();
        $this->patchJson("/api/tasks/{$this->sharedTaskB->uuid}", ['tags' => ['cliente-b']])->assertOk();

        Sanctum::actingAs($this->clientUserA);

        $tags = collect($this->getJson('/api/workspace/tags')->assertOk()->json('data'))->pluck('name');
        $this->assertSame(['layout'], $tags->all());
    }

    public function test_column_of_another_tenant_is_not_found(): void
    {
        $otherTenant = $this->createTenantWithRoles(['domain' => 'outro.com.br']);
        Sanctum::actingAs($this->createAdmin($otherTenant));

        $column = $this->columnOf($this->projectA, ColumnKind::OPEN);

        $this->patchJson("/api/board/columns/{$column}", ['name' => 'X'])->assertNotFound();
        $this->deleteJson("/api/board/columns/{$column}")->assertNotFound();
    }

    public function test_client_change_history_is_internal(): void
    {
        Sanctum::actingAs($this->professional);

        $project = $this->postJson('/api/projects', ['name' => 'Sem tarefas', 'client_id' => $this->clientA->uuid])->json('data.id');
        $this->patchJson("/api/projects/{$project}", ['client_id' => $this->clientB->uuid])->assertOk();

        Sanctum::actingAs($this->clientUserB);

        $this->getJson("/api/projects/{$project}/activity")
            ->assertOk()
            ->assertJsonMissing(['to' => 'Cliente B'])
            ->assertDontSee('Cliente A');
    }
}
