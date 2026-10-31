<?php

namespace Tests\Feature\Workspace;

use App\Modules\Workspace\Enums\ColumnKind;
use App\Modules\Workspace\Enums\Visibility;
use App\Modules\Workspace\Models\Activity;
use App\Modules\Workspace\Models\BoardColumn;
use App\Modules\Workspace\Models\Client;
use App\Modules\Workspace\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithWorkspace;
use Tests\TestCase;

class WorkspaceFeatureTest extends TestCase
{
    use InteractsWithWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorkspace();
        Sanctum::actingAs($this->professional);
    }

    public function test_creates_and_updates_project_with_default_board_and_members(): void
    {
        $member = $this->createProfessional($this->tenant);

        $id = $this->postJson('/api/projects', [
            'name' => 'Site institucional',
            'client_id' => $this->clientA->uuid,
            'starts_at' => '2026-11-01',
            'due_at' => '2026-12-15',
            'member_ids' => [$member->uuid],
            'internal_notes' => 'Orçamento fechado',
        ])
            ->assertCreated()
            ->assertJsonPath('data.client.id', $this->clientA->uuid)
            ->assertJsonPath('data.status', 'planning')
            ->assertJsonCount(4, 'data.board.columns')
            ->assertJsonPath('data.members.0.id', $member->uuid)
            ->json('data.id');

        $this->patchJson("/api/projects/{$id}", ['status' => 'active', 'internal_notes' => 'Revisado'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        // Histórico: alteração de status é compartilhada, a de nota interna não.
        $shared = Activity::query()->where('event', 'project.updated')->where('visibility', 'shared')->first();
        $internal = Activity::query()->where('event', 'project.updated')->where('visibility', 'internal')->first();
        $this->assertSame(['from' => 'planning', 'to' => 'active'], $shared->changes['status']);
        $this->assertArrayHasKey('internal_notes', $internal->changes);
        $this->assertSame('api', $shared->source->value);

        // Membro é notificado da inclusão e da atualização do projeto.
        $kinds = $member->notifications()->get()->pluck('data.kind')->sort()->values()->all();
        $this->assertSame(['project_member_added', 'project_updated'], $kinds);
    }

    public function test_project_validation_rejects_foreign_or_client_users_as_members(): void
    {
        $this->postJson('/api/projects', [
            'name' => 'X',
            'client_id' => $this->clientA->uuid,
            'member_ids' => [$this->clientUserA->uuid],
        ])->assertUnprocessable()->assertJsonValidationErrors(['member_ids.0']);

        $other = $this->createTenantWithRoles(['domain' => 'outro.com.br']);
        $foreignClient = Client::factory()->for($other)->create();

        $this->postJson('/api/projects', ['name' => 'X', 'client_id' => $foreignClient->uuid])
            ->assertUnprocessable()->assertJsonValidationErrors(['client_id']);
    }

    public function test_cannot_change_client_of_project_with_tasks(): void
    {
        $this->patchJson("/api/projects/{$this->projectA->uuid}", ['client_id' => $this->clientB->uuid])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['client_id']);
    }

    public function test_archives_and_restores_project_and_filters_archived(): void
    {
        $this->postJson("/api/projects/{$this->projectB->uuid}/archive")->assertOk()->assertJsonPath('data.is_archived', true);

        $this->getJson('/api/projects')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/projects?archived=only')->assertJsonPath('data.0.id', $this->projectB->uuid);

        // Projeto arquivado não recebe tarefas novas.
        $this->postJson("/api/projects/{$this->projectB->uuid}/tasks", ['title' => 'Nova'])->assertForbidden();

        $this->postJson("/api/projects/{$this->projectB->uuid}/restore")->assertOk()->assertJsonPath('data.is_archived', false);
    }

    public function test_creates_task_with_assignee_tags_and_notifies(): void
    {
        $assignee = $this->createProfessional($this->tenant);

        $this->postJson("/api/projects/{$this->projectA->uuid}/tasks", [
            'title' => 'Criar landing page',
            'priority' => 'high',
            'visibility' => 'shared',
            'due_at' => '2026-11-20',
            'estimated_hours' => 6.5,
            'assignee_id' => $assignee->uuid,
            'tags' => ['Frontend', 'design', 'frontend'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.assignee.id', $assignee->uuid)
            ->assertJsonPath('data.status.kind', 'open')
            ->assertJsonPath('data.client.id', $this->clientA->uuid)
            ->assertJsonCount(2, 'data.tags');

        $this->assertSame('task_assigned', $assignee->notifications()->first()->data['kind']);
    }

    public function test_assigns_task_and_changes_priority_with_history(): void
    {
        $assignee = $this->createProfessional($this->tenant);

        $this->postJson("/api/tasks/{$this->sharedTaskA->uuid}/assign", [
            'assignee_id' => $assignee->uuid,
            'participant_ids' => [$this->professional->uuid],
        ])->assertOk()->assertJsonPath('data.assignee.id', $assignee->uuid);

        $this->patchJson("/api/tasks/{$this->sharedTaskA->uuid}", ['priority' => 'urgent'])
            ->assertOk()->assertJsonPath('data.priority', 'urgent');

        $events = collect($this->getJson("/api/tasks/{$this->sharedTaskA->uuid}/activity")->json('data'))->pluck('event');
        $this->assertTrue($events->contains('task.assigned'));
        $this->assertTrue($events->contains('task.updated'));

        // Cliente não pode ser responsável.
        $this->postJson("/api/tasks/{$this->sharedTaskA->uuid}/assign", ['assignee_id' => $this->clientUserA->uuid])
            ->assertUnprocessable();
    }

    public function test_moves_task_between_columns_and_tracks_completion(): void
    {
        $done = $this->columnOf($this->projectA, ColumnKind::DONE);
        $this->sharedTaskA->forceFill(['created_by' => $this->professional->getKey()])->save();

        $this->postJson("/api/tasks/{$this->sharedTaskA->uuid}/move", ['column_id' => $done, 'position' => 0])
            ->assertOk()
            ->assertJsonPath('data.status.id', $done)
            ->assertJsonPath('data.status.kind', 'done');

        $this->assertNotNull($this->sharedTaskA->fresh()->completed_at);

        // Cliente da tarefa compartilhada é notificado da conclusão.
        $this->assertSame('task_completed', $this->clientUserA->notifications()->first()?->data['kind']);

        // Coluna de outro quadro é rejeitada.
        $this->postJson("/api/tasks/{$this->sharedTaskA->uuid}/move", [
            'column_id' => $this->columnOf($this->projectB, ColumnKind::OPEN),
        ])->assertUnprocessable()->assertJsonValidationErrors(['column_id']);

        // Voltar para "aberto" limpa a conclusão.
        $this->postJson("/api/tasks/{$this->sharedTaskA->uuid}/move", [
            'column_id' => $this->columnOf($this->projectA, ColumnKind::OPEN),
        ])->assertOk();
        $this->assertNull($this->sharedTaskA->fresh()->completed_at);
    }

    public function test_reorders_tasks_within_column(): void
    {
        $open = $this->columnOf($this->projectA, ColumnKind::OPEN);
        $third = Task::factory()->forProject($this->projectA)->create();

        $this->postJson("/api/tasks/{$third->uuid}/move", ['column_id' => $open, 'position' => 0])->assertOk();

        $order = Task::query()->where('project_id', $this->projectA->getKey())
            ->where('board_column_id', BoardColumn::query()->where('uuid', $open)->value('id'))
            ->orderBy('position')->pluck('id')->all();

        $this->assertSame($third->getKey(), $order[0]);
        $this->assertCount(3, $order);
    }

    public function test_configures_board_columns(): void
    {
        $boardId = $this->postJson('/api/boards', [
            'name' => 'Desenvolvimento',
            'columns' => [
                ['name' => 'Backlog', 'kind' => 'open', 'color' => '#111111'],
                ['name' => 'Feito', 'kind' => 'done'],
            ],
        ])->assertCreated()->assertJsonCount(2, 'data.columns')->json('data.id');

        $review = $this->postJson("/api/boards/{$boardId}/columns", ['name' => 'Revisão', 'kind' => 'waiting', 'position' => 1])
            ->assertCreated()->assertJsonPath('data.position', 1)->json('data.id');

        $this->postJson("/api/boards/{$boardId}/columns/{$review}/move", ['position' => 0])->assertOk()->assertJsonPath('data.position', 0);

        $this->patchJson("/api/boards/{$boardId}/columns/{$review}", ['name' => 'Em revisão', 'color' => '#ff0000'])
            ->assertOk()->assertJsonPath('data.name', 'Em revisão');

        $names = collect($this->getJson("/api/boards/{$boardId}")->json('data.columns'))->pluck('name');
        $this->assertSame(['Em revisão', 'Backlog', 'Feito'], $names->all());

        $this->patchJson("/api/boards/{$boardId}/columns/{$review}", ['color' => 'red'])->assertUnprocessable();
    }

    public function test_deleting_column_with_tasks_requires_destination(): void
    {
        $board = $this->projectA->board;
        $open = $board->columns()->where('kind', 'open')->first();
        $waiting = $board->columns()->where('kind', 'waiting')->first();

        $this->deleteJson("/api/boards/{$board->uuid}/columns/{$open->uuid}")
            ->assertUnprocessable()->assertJsonValidationErrors(['move_tasks_to']);

        $this->deleteJson("/api/boards/{$board->uuid}/columns/{$open->uuid}", ['move_tasks_to' => $waiting->uuid])->assertOk();

        $this->assertSame($waiting->getKey(), $this->sharedTaskA->fresh()->board_column_id);
        $this->assertDatabaseMissing('board_columns', ['id' => $open->getKey()]);

        // Coluna de outro quadro via rota aninhada: 404 (scoped binding).
        $foreign = $this->projectB->board->columns()->first();
        $this->deleteJson("/api/boards/{$board->uuid}/columns/{$foreign->uuid}")->assertNotFound();
    }

    public function test_subtasks_progress_and_completion(): void
    {
        $url = "/api/tasks/{$this->sharedTaskA->uuid}/subtasks";

        $first = $this->postJson($url, ['title' => 'Wireframe'])->assertCreated()->json('data.id');
        $this->postJson($url, ['title' => 'Layout', 'assignee_id' => $this->professional->uuid])->assertCreated();

        $this->patchJson("{$url}/{$first}", ['status' => 'done'])->assertOk()->assertJsonPath('data.status', 'done');

        $this->getJson("/api/tasks/{$this->sharedTaskA->uuid}")
            ->assertJsonPath('data.subtasks_summary.total', 2)
            ->assertJsonPath('data.subtasks_summary.done', 1)
            ->assertJsonPath('data.subtasks_summary.percent', 50);

        $this->assertTrue(Activity::query()->where('event', 'subtask.completed')->exists());

        $this->deleteJson("{$url}/{$first}")->assertOk();
        $this->assertDatabaseMissing('subtasks', ['uuid' => $first]);
    }

    public function test_comments_respect_task_visibility(): void
    {
        // Em tarefa interna, comentário é sempre interno.
        $this->postJson("/api/tasks/{$this->internalTaskA->uuid}/comments", ['body' => 'nota', 'visibility' => 'shared'])
            ->assertCreated()->assertJsonPath('data.visibility', 'internal');

        $this->postJson("/api/tasks/{$this->sharedTaskA->uuid}/comments", ['body' => 'Olá cliente', 'visibility' => 'shared'])
            ->assertCreated()->assertJsonPath('data.visibility', 'shared');

        // Cliente notificado só do comentário compartilhado.
        $this->assertSame(1, $this->clientUserA->notifications()->count());
        $this->assertSame(0, $this->clientUserB->notifications()->count());
    }

    public function test_task_filters_search_and_pagination(): void
    {
        Task::factory()->forProject($this->projectB)->count(25)->create(['priority' => 'low']);
        Task::factory()->forProject($this->projectA)->create(['title' => 'Integração de pagamento', 'priority' => 'urgent', 'due_at' => now()->subDay()]);

        $this->getJson('/api/tasks?per_page=10')->assertJsonPath('meta.per_page', 10)->assertJsonPath('meta.total', 29);
        $this->getJson('/api/tasks?per_page=10&page=3')->assertJsonCount(9, 'data');
        $this->getJson('/api/tasks?per_page=1000')->assertUnprocessable();

        $this->getJson('/api/tasks?search=pagamento')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/tasks?priority[]=urgent')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/tasks?overdue=1')->assertJsonPath('meta.total', 1);
        $this->getJson("/api/tasks?client={$this->clientB->uuid}")->assertJsonPath('meta.total', 26);
        $this->getJson("/api/tasks?project={$this->projectA->uuid}&visibility=internal")->assertJsonPath('meta.total', 1);
        $this->getJson('/api/tasks?sort=priority&direction=desc')->assertJsonPath('data.0.priority', 'urgent');
    }

    public function test_project_progress_counts_done_tasks(): void
    {
        Task::factory()->forProject($this->projectA, ColumnKind::DONE)->create();

        $this->getJson("/api/projects/{$this->projectA->uuid}")
            ->assertJsonPath('data.progress.total', 3)
            ->assertJsonPath('data.progress.done', 1)
            ->assertJsonPath('data.progress.percent', 33);

        // Para o cliente, só tarefas compartilhadas contam.
        Sanctum::actingAs($this->clientUserA);
        $this->getJson("/api/projects/{$this->projectA->uuid}")
            ->assertJsonPath('data.progress.total', 2)
            ->assertJsonPath('data.progress.percent', 50);
    }

    public function test_client_kanban_shows_only_shared_tasks(): void
    {
        Sanctum::actingAs($this->clientUserA);

        $ids = collect($this->getJson("/api/projects/{$this->projectA->uuid}/board")->assertOk()->json('data.tasks'))->pluck('id');

        $this->assertSame([$this->sharedTaskA->uuid], $ids->all());
    }

    public function test_activity_hides_internal_events_from_client(): void
    {
        $this->patchJson("/api/tasks/{$this->internalTaskA->uuid}", ['title' => 'Interna renomeada'])->assertOk();
        $this->patchJson("/api/tasks/{$this->sharedTaskA->uuid}", ['title' => 'Compartilhada renomeada'])->assertOk();

        Sanctum::actingAs($this->clientUserA);

        $tasks = collect($this->getJson("/api/projects/{$this->projectA->uuid}/activity")->json('data'))->pluck('task.id')->filter();
        $this->assertSame([$this->sharedTaskA->uuid], $tasks->unique()->values()->all());
    }

    public function test_manages_clients_and_client_users(): void
    {
        $id = $this->postJson('/api/clients', ['name' => 'ACME', 'email' => 'contato@acme.com', 'document' => '11.222.333/0001-81'])
            ->assertCreated()->assertJsonPath('data.document', '11222333000181')->json('data.id');

        $userId = $this->postJson("/api/clients/{$id}/users", [
            'name' => 'Maria ACME', 'email' => 'maria@acme.com', 'password' => 'segredo123',
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/clients/{$id}")->assertJsonPath('data.users.0.id', $userId);

        $this->deleteJson("/api/clients/{$id}/users/{$this->clientUserA->uuid}")->assertNotFound();
        $this->deleteJson("/api/clients/{$id}/users/{$userId}")->assertOk();
        $this->assertSoftDeleted('users', ['uuid' => $userId]);

        // Exclusão exige client.delete (somente Administrador).
        $this->deleteJson("/api/clients/{$id}")->assertForbidden();
        Sanctum::actingAs($this->createAdmin($this->tenant));
        $this->deleteJson("/api/clients/{$this->clientA->uuid}")->assertUnprocessable();
        $this->deleteJson("/api/clients/{$id}")->assertOk();
    }

    public function test_notification_center(): void
    {
        $assignee = $this->createProfessional($this->tenant);
        $this->postJson("/api/tasks/{$this->sharedTaskA->uuid}/assign", ['assignee_id' => $assignee->uuid])->assertOk();

        Sanctum::actingAs($assignee);

        $id = $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('meta.unread', 1)
            ->assertJsonPath('data.0.kind', 'task_assigned')
            ->assertJsonPath('data.0.task_id', $this->sharedTaskA->uuid)
            ->json('data.0.id');

        $this->postJson("/api/notifications/{$id}/read")->assertOk();
        $this->getJson('/api/notifications/unread-count')->assertJsonPath('data.unread', 0);

        // Notificação de outro usuário: 404.
        Sanctum::actingAs($this->professional);
        $this->postJson("/api/notifications/{$id}/read")->assertNotFound();
    }

    public function test_every_workspace_route_is_authorized_by_policy(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_starts_with(
            $route->getActionName(),
            'App\\Modules\\Workspace\\Http\\Controllers\\',
        ));

        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth:sanctum', $middleware, $route->uri());

            if (str_contains($route->getActionName(), 'NotificationController')) {
                continue; // escopo pelo próprio usuário autenticado
            }

            $this->assertTrue(
                collect($middleware)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'can:')),
                "Rota sem Policy: {$route->uri()}",
            );
        }
    }

    public function test_visibility_change_to_internal_hides_task_from_client(): void
    {
        $this->patchJson("/api/tasks/{$this->sharedTaskA->uuid}", ['visibility' => Visibility::INTERNAL->value])->assertOk();

        Sanctum::actingAs($this->clientUserA);
        $this->getJson("/api/tasks/{$this->sharedTaskA->uuid}")->assertNotFound();
    }
}
