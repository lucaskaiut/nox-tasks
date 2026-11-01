<?php

namespace Database\Seeders;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Models\Role;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\CurrentTenant;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Models\Task;
use App\Modules\Workspace\Services\BoardService;
use App\Modules\Workspace\Services\ClientService;
use App\Modules\Workspace\Services\CommentService;
use App\Modules\Workspace\Services\ProjectService;
use App\Modules\Workspace\Services\SubtaskService;
use App\Modules\Workspace\Services\TaskService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Dados de demonstração do Workspace, criados pelos próprios serviços
 * (gerando histórico real). Login de cliente: cliente@demo.localhost / password.
 */
class WorkspaceDemoSeeder extends Seeder
{
    public function run(Tenant $tenant, User $admin): void
    {
        app(CurrentTenant::class)->set($tenant);
        Auth::setUser($admin);

        $professional = User::query()->create([
            'name' => 'Ana Ribeiro',
            'email' => 'ana@demo.localhost',
            'password' => 'password',
        ]);
        $professional->assignRole(Role::query()->where('name', DefaultRole::PROFESSIONAL->value)->firstOrFail());

        $clients = app(ClientService::class);
        $acme = $clients->create(['name' => 'Acme Indústria', 'email' => 'contato@acme.localhost', 'notes' => 'Cliente desde 2024.']);
        $bistro = $clients->create(['name' => 'Bistrô Central', 'email' => 'ola@bistro.localhost']);

        $clients->createUser($acme, ['name' => 'Carlos (Acme)', 'email' => 'cliente@demo.localhost', 'password' => 'password']);

        $board = app(BoardService::class)->current();
        $board->update(['description' => 'Quadro único com as tarefas de todos os projetos.']);
        app(BoardService::class)->addColumn($board, ['name' => 'Em revisão', 'color' => '#7c3aed', 'kind' => 'open', 'position' => 2]);

        $projects = app(ProjectService::class);
        $tasks = app(TaskService::class);

        $site = $projects->create($admin, [
            'name' => 'Novo site institucional',
            'description' => 'Redesign completo do site com CMS e área de notícias.',
            'client_id' => $acme->uuid,
            'status' => 'active',
            'starts_at' => today()->subDays(20)->toDateString(),
            'due_at' => today()->addDays(25)->toDateString(),
            'member_ids' => [$admin->uuid, $professional->uuid],
            'internal_notes' => 'Margem apertada — evitar escopo extra.',
        ]);

        $app = $projects->create($admin, [
            'name' => 'Cardápio digital',
            'client_id' => $bistro->uuid,
            'status' => 'planning',
            'due_at' => today()->addDays(60)->toDateString(),
            'member_ids' => [$professional->uuid],
        ]);

        // [projeto, título, prioridade, visibilidade, coluna do quadro geral, prazo em dias]
        $seed = [
            [$site, 'Definir arquitetura de informação', 'high', 'shared', 'Concluído', -10],
            [$site, 'Layout da home', 'urgent', 'shared', 'Em andamento', 2],
            [$site, 'Integração com CMS', 'high', 'internal', 'Em andamento', -1],
            [$site, 'Revisão do mapa do site', 'normal', 'internal', 'Em revisão', 3],
            [$site, 'Aprovar textos institucionais', 'normal', 'shared', 'Aguardando retorno', 5],
            [$site, 'Configurar analytics', 'low', 'internal', 'A fazer', 15],
            [$app, 'Briefing inicial', 'normal', 'shared', 'Concluído', -5],
            [$app, 'Levantar itens do cardápio', 'normal', 'shared', 'A fazer', 10],
            [$app, 'Protótipo navegável', 'high', 'internal', 'A fazer', 20],
            [$app, 'Fotos dos pratos', 'normal', 'shared', 'Aguardando retorno', 7],
        ];

        $columns = $board->columns()->get()->keyBy('name');

        foreach ($seed as [$project, $title, $priority, $visibility, $column, $dueIn]) {
            $tasks->create($admin, $project, [
                'title' => $title,
                'priority' => $priority,
                'visibility' => $visibility,
                'column_id' => $columns[$column]->uuid,
                'due_at' => today()->addDays($dueIn)->toDateString(),
                'assignee_id' => $professional->uuid,
                'tags' => $visibility === 'internal' ? ['interno'] : [$project->is($site) ? 'site' : 'cardápio'],
            ]);
        }

        $home = Task::query()->where('title', 'Layout da home')->first();
        app(SubtaskService::class)->create($home, ['title' => 'Wireframe', 'status' => 'done']);
        app(SubtaskService::class)->create($home, ['title' => 'Versão mobile']);
        app(CommentService::class)->create($admin, $home, 'Primeira versão enviada para aprovação.', 'shared');
        app(CommentService::class)->create($admin, $home, 'Atenção ao prazo da fonte licenciada.', 'internal');
    }
}
