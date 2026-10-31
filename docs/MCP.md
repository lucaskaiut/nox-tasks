# Servidor MCP — Nox Tasks

Servidor [Model Context Protocol](https://modelcontextprotocol.io) construído com `laravel/mcp`. Permite que agentes de IA consultem e operem clientes, projetos, tarefas, subtarefas e quadros Kanban **em nome do usuário que autorizou a conexão**, com exatamente as mesmas regras da API.

- Endpoint: `POST {APP_URL}/mcp/workspace` (transporte HTTP streamable)
- Código: `api/app/Modules/Mcp/` · rota: `api/routes/ai.php`
- Testes: `api/tests/Feature/Mcp/McpWorkspaceTest.php`

## Conectando um agente

1. No Workspace, abra **Conexões de IA** e gere um token (exige a permissão `mcp.use`). O token é exibido uma única vez.
2. Configure o cliente MCP com o header `Authorization: Bearer <token>`.

Claude Code:

```bash
claude mcp add --transport http nox-tasks http://localhost/mcp/workspace \
  --header "Authorization: Bearer <token>"
```

Clientes com arquivo de configuração (Claude Desktop via `mcp-remote`, Cursor etc.):

```json
{
  "mcpServers": {
    "nox-tasks": {
      "url": "http://localhost/mcp/workspace",
      "headers": { "Authorization": "Bearer <token>" }
    }
  }
}
```

> Conectores web que exigem OAuth (claude.ai, ChatGPT) não são suportados nesta versão — a autenticação usa tokens pessoais Sanctum.

## Modelo de segurança

| Camada | Regra |
|---|---|
| Identidade | Token pessoal Sanctum emitido **somente** para MCP (abilities exatamente `["mcp"]`). Tokens de login (`*`), sessões e tokens de tenant (`api_*`) são recusados. A identidade nunca vem de argumentos. |
| Escopo do token | Tokens MCP são recusados no restante da API REST (`RestrictMcpTokens`). |
| Tenant | Resolvido pelo mesmo middleware da API. Sem tenant resolvido, as tools falham fechadas. |
| Autorização | As mesmas Policies da API (`ClientPolicy`, `ProjectPolicy`, `TaskPolicy`, `BoardPolicy`). Usuário vinculado a cliente nunca acessa outro cliente, tarefas internas ou ações de gestão, mesmo que um papel lhe conceda permissões a mais. |
| Listagens | Partem de `visibleTo($user)`; filtros enviados pelo agente só restringem o resultado. |
| Erros | Recurso inexistente e recurso fora do escopo retornam o mesmo `Recurso não encontrado.` Exceções inesperadas não vazam detalhes. |
| Validação | Mesmas regras dos FormRequests (`WorkspaceRules`). |
| Catálogo | Cada tool só é listada para quem pode usá-la (`shouldRegister`). A autorização continua sendo verificada na execução. |
| Limites | Rate limit por usuário (`MCP_RATE_LIMIT`, padrão 120/min), paginação (máx. 50 por página) e respostas limitadas a 200 KB. |
| Auditoria | Cada chamada vira um `audit_logs` (`mcp_tool_called`, com resultado e argumentos resumidos). Alterações no domínio entram no histórico (`activities`) com `source = mcp`. |

## Tools

IDs são UUIDs. Listagens aceitam `page` e `per_page` e retornam `{ data, meta: { page, per_page, total, last_page, has_more } }`.

| Tool | Quem | Descrição |
|---|---|---|
| `whoami` | todos | Usuário da conexão, perfil, organização, cliente vinculado e permissões |
| `get_dashboard` | todos | Indicadores e listas do dashboard, restritos ao escopo |
| `list_members` | profissional | Profissionais atribuíveis (IDs para responsáveis e participantes) |
| `list_clients` / `get_client` | profissional | Clientes |
| `create_client` / `update_client` | profissional | Cadastro de clientes |
| `list_projects` / `get_project` | todos | Projetos e progresso (o cliente só vê os próprios) |
| `create_project` / `update_project` | profissional | Gestão de projetos |
| `archive_project` | profissional | Arquiva o projeto, ou reativa com `restore: true` |
| `list_tasks` / `get_task` | todos | Tarefas (o cliente só vê as compartilhadas dos próprios projetos) |
| `create_task` | todos | O cliente cria sempre como `shared` e sem responsável |
| `update_task` | profissional | Dados descritivos, prioridade, visibilidade, prazos e tags |
| `move_task` | profissional | Muda de coluna (status) e posição; coluna `done` conclui a tarefa |
| `assign_task` | profissional | Define o responsável e os participantes |
| `delete_task` | `task.delete` | Destrutiva (exclusão lógica) |
| `list_subtasks` | todos | Subtarefas e percentual de conclusão (herdam o acesso da tarefa) |
| `create_subtask` / `update_subtask` / `delete_subtask` | profissional | Gestão de subtarefas |
| `list_boards` / `get_board` | todos | Quadros. `get_board` com `project_id` retorna o Kanban com as tarefas por coluna |
| `create_board` / `update_board` | profissional | Gestão de quadros |
| `list_board_columns` | todos | Colunas em ordem (`kind`: open, waiting, done, cancelled) |
| `create_board_column` / `update_board_column` / `move_board_column` | profissional | Configuração de colunas |
| `delete_board_column` | profissional | Destrutiva. Exige `move_tasks_to` se houver tarefas na coluna |
| `list_task_comments` / `create_task_comment` | todos | Comentários (`internal` nunca chega ao cliente) |
| `list_task_activity` / `list_project_activity` | todos | Histórico com antes/depois, autor e origem |

"Profissional" significa um usuário sem vínculo com cliente que tem a permissão correspondente (`client.*`, `project.*`, `task.*`, `board.manage`).

## Resources

URI templates, autorizados pelas mesmas Policies (não existem resources globais):

- `nox://projects/{project_id}`: contexto do projeto, com dados, progresso, tarefas abertas e atividades recentes
- `nox://projects/{project_id}/board`: Kanban do projeto
- `nox://projects/{project_id}/activity`: últimas 50 atividades
- `nox://tasks/{task_id}`: resumo da tarefa, com subtarefas, comentários visíveis e histórico

## Prompts

- `analyze_project(project_id)`: riscos, gargalos e próximos passos
- `overdue_tasks(project_id?)`: tarefas atrasadas e plano de recuperação
- `organize_tasks(project_id)`: sugestões de organização do Kanban, executadas só após confirmação do usuário
- `project_progress_summary(project_id, audience)`: resumo interno ou para o cliente. Com `audience=client`, os dados internos são removidos antes de chegar ao modelo
