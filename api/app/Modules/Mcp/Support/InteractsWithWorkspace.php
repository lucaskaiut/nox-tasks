<?php

namespace App\Modules\Mcp\Support;

use App\Modules\User\Models\User;
use App\Modules\Workspace\Models\Board;
use App\Modules\Workspace\Models\Client;
use App\Modules\Workspace\Models\Project;
use App\Modules\Workspace\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Helpers compartilhados por Tools, Resources e Prompts do MCP.
 * Toda busca por ID é limitada ao tenant (TenantScope) e seguida de
 * autorização via Policy; ID inválido e acesso negado retornam o mesmo erro.
 */
trait InteractsWithWorkspace
{
    protected const NOT_FOUND = 'Recurso não encontrado.';

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $model
     * @return T
     */
    protected function findOrFail(string $model, mixed $uuid): Model
    {
        if (! is_string($uuid) || ! Str::isUuid($uuid)) {
            throw new ModelNotFoundException;
        }

        return $model::query()->where('uuid', $uuid)->firstOrFail();
    }

    /**
     * @param  array<int, mixed>|mixed  $arguments
     */
    protected function authorizeFor(User $user, string $ability, mixed $arguments): void
    {
        Gate::forUser($user)->authorize($ability, $arguments);
    }

    protected function authorizedTask(User $user, mixed $uuid, string $ability = 'view'): Task
    {
        $task = $this->findOrFail(Task::class, $uuid);
        $this->authorizeFor($user, $ability, $task);

        return $task;
    }

    protected function authorizedProject(User $user, mixed $uuid, string $ability = 'view'): Project
    {
        $project = $this->findOrFail(Project::class, $uuid);
        $this->authorizeFor($user, $ability, $project);

        return $project;
    }

    protected function authorizedClient(User $user, mixed $uuid, string $ability = 'view'): Client
    {
        $client = $this->findOrFail(Client::class, $uuid);
        $this->authorizeFor($user, $ability, $client);

        return $client;
    }

    protected function authorizedBoard(User $user, mixed $uuid, string $ability = 'view'): Board
    {
        $board = $this->findOrFail(Board::class, $uuid);
        $this->authorizeFor($user, $ability, $board);

        return $board;
    }

    /**
     * @param  class-string<JsonResource>  $resource
     * @return array<string, mixed>
     */
    protected function item(string $resource, mixed $model): array
    {
        return $resource::make($model)->resolve(request());
    }

    /**
     * @param  class-string<JsonResource>  $resource
     * @return array{data: list<array<string, mixed>>, meta: array<string, int|bool>}
     */
    protected function page(LengthAwarePaginator $paginator, string $resource): array
    {
        return [
            'data' => $resource::collection($paginator->getCollection())->resolve(request()),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ];
    }

    /**
     * @param  class-string<JsonResource>  $resource
     * @return list<array<string, mixed>>
     */
    protected function collection(string $resource, iterable $items): array
    {
        return $resource::collection(collect($items))->resolve(request());
    }

    /**
     * Parâmetros de paginação comuns às ferramentas de listagem.
     *
     * @return array<string, mixed>
     */
    protected function paginationSchema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->integer()->min(1)->description('Página (padrão 1).'),
            'per_page' => $schema->integer()->min(1)->max(50)->description('Itens por página (padrão 15, máximo 50).'),
        ];
    }

    protected function perPage(mixed $value, int $default = 15): int
    {
        return min(max((int) ($value ?? $default), 1), 50);
    }

    /**
     * Aplica a página solicitada ao paginador do Laravel.
     */
    protected function usePage(mixed $page): void
    {
        $page = max(1, (int) ($page ?? 1));
        Paginator::currentPageResolver(fn () => $page);
    }
}
