<?php

namespace App\Modules\Mcp\Servers;

use App\Modules\Mcp\Prompts;
use App\Modules\Mcp\Resources;
use App\Modules\Mcp\Tools;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Nox Tasks')]
#[Version('1.0.0')]
#[Instructions(<<<'MD'
Servidor MCP do Nox Tasks — gestão de clientes, projetos, tarefas, subtarefas e quadros Kanban.

- Todas as operações são executadas em nome do usuário que autorizou a conexão, com as mesmas permissões dele na plataforma. Comece com `whoami`.
- IDs são UUIDs. Use as ferramentas de listagem para descobri-los (list_projects, list_tasks, list_members, get_board).
- Há um único quadro Kanban geral, compartilhado por todos os projetos. O status de uma tarefa é a coluna desse quadro; use `move_task` para alterá-lo e `get_board` (opcionalmente com project_id) para ver as colunas e tarefas.
- Tarefas e comentários "internal" nunca são vistos por usuários de cliente; use "shared" apenas para o que o cliente deve acompanhar.
- Listagens são paginadas (page, per_page ≤ 50).
- Antes de ações destrutivas (delete_*) ou alterações em massa, peça confirmação ao usuário.
- "Recurso não encontrado" significa que o recurso não existe ou não está acessível para este usuário.
MD)]
class WorkspaceServer extends Server
{
    public int $defaultPaginationLength = 50;

    public int $maxPaginationLength = 100;

    protected array $tools = [
        Tools\Boards\CreateBoardColumn::class,
        Tools\Boards\DeleteBoardColumn::class,
        Tools\Boards\GetBoard::class,
        Tools\Boards\ListBoardColumns::class,
        Tools\Boards\MoveBoardColumn::class,
        Tools\Boards\UpdateBoard::class,
        Tools\Boards\UpdateBoardColumn::class,
        Tools\Clients\CreateClient::class,
        Tools\Clients\GetClient::class,
        Tools\Clients\ListClients::class,
        Tools\Clients\UpdateClient::class,
        Tools\Comments\CreateTaskComment::class,
        Tools\Comments\ListProjectActivity::class,
        Tools\Comments\ListTaskActivity::class,
        Tools\Comments\ListTaskComments::class,
        Tools\Projects\ArchiveProject::class,
        Tools\Projects\CreateProject::class,
        Tools\Projects\GetProject::class,
        Tools\Projects\ListProjects::class,
        Tools\Projects\UpdateProject::class,
        Tools\Subtasks\CreateSubtask::class,
        Tools\Subtasks\DeleteSubtask::class,
        Tools\Subtasks\ListSubtasks::class,
        Tools\Subtasks\UpdateSubtask::class,
        Tools\Tasks\AssignTask::class,
        Tools\Tasks\CreateTask::class,
        Tools\Tasks\DeleteTask::class,
        Tools\Tasks\GetTask::class,
        Tools\Tasks\ListTasks::class,
        Tools\Tasks\MoveTask::class,
        Tools\Tasks\UpdateTask::class,
        Tools\Workspace\GetDashboard::class,
        Tools\Workspace\ListMembers::class,
        Tools\Workspace\WhoAmI::class,
    ];

    protected array $resources = [
        Resources\ProjectContextResource::class,
        Resources\ProjectBoardResource::class,
        Resources\ProjectActivityResource::class,
        Resources\TaskSummaryResource::class,
    ];

    protected array $prompts = [
        Prompts\AnalyzeProjectPrompt::class,
        Prompts\OverdueTasksPrompt::class,
        Prompts\OrganizeTasksPrompt::class,
        Prompts\ProjectProgressPrompt::class,
    ];
}
