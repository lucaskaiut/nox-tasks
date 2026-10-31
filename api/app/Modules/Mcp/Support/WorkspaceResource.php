<?php

namespace App\Modules\Mcp\Support;

use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;
use Throwable;

/**
 * Resource de contexto do Workspace. Sempre parametrizado por ID (URI
 * template) e autorizado pelas mesmas Policies das Tools — não há
 * resources globais que permitam contornar o escopo do usuário.
 */
abstract class WorkspaceResource extends Resource implements HasUriTemplate
{
    use InteractsWithWorkspace;

    protected string $mimeType = 'application/json';

    abstract protected function template(): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected function content(Request $request, User $user): array;

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate($this->template());
    }

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! TenantContext::isResolved()) {
            return Response::error('Não autenticado.');
        }

        try {
            return Response::json($this->content($request, $user));
        } catch (AuthorizationException|ModelNotFoundException) {
            return Response::error(self::NOT_FOUND);
        } catch (Throwable $e) {
            report($e);

            return Response::error('Erro interno ao ler o recurso.');
        }
    }
}
