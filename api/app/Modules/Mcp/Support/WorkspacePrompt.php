<?php

namespace App\Modules\Mcp\Support;

use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use Throwable;

/**
 * Prompt que embute um retrato autorizado dos dados no texto enviado ao
 * agente. Os dados vêm dos mesmos escopos/Policies das Tools.
 */
abstract class WorkspacePrompt extends Prompt
{
    use InteractsWithWorkspace;

    abstract protected function compose(Request $request, User $user): string;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! TenantContext::isResolved()) {
            return Response::error('Não autenticado.');
        }

        try {
            return Response::text($this->compose($request, $user));
        } catch (AuthorizationException|ModelNotFoundException) {
            return Response::error(self::NOT_FOUND);
        } catch (Throwable $e) {
            report($e);

            return Response::error('Erro interno ao montar o prompt.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function json(array $data): string
    {
        return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
