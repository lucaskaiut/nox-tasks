<?php

namespace App\Modules\Mcp\Support;

use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Enums\OperationSource;
use App\Modules\Workspace\Support\OperationContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Support\ValidationMessages;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Base das Tools do Workspace. Camada de entrada apenas:
 *
 *  1. identifica o usuário EXCLUSIVAMENTE pelo contexto autenticado da conexão
 *     (nunca por argumentos) e exige tenant resolvido (falha fechada);
 *  2. delega validação (WorkspaceRules), autorização (Policies) e regras de
 *     negócio (Services) aos mesmos componentes da API HTTP;
 *  3. devolve resultado estruturado, com limite de tamanho;
 *  4. traduz erros sem revelar a existência de recursos fora do escopo;
 *  5. registra cada chamada na auditoria e marca a origem MCP no histórico.
 */
abstract class WorkspaceTool extends Tool
{
    use InteractsWithWorkspace;

    public const MAX_RESPONSE_BYTES = 200_000;

    /**
     * @return array<string, mixed>
     */
    abstract protected function run(Request $request, User $user): array;

    /**
     * Ferramenta listada apenas a quem pode utilizá-la (a autorização real
     * continua acontecendo em run()).
     */
    protected function availableTo(User $user): bool
    {
        return true;
    }

    public function shouldRegister(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof User && $this->availableTo($user);
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Não autenticado.');
        }

        if (! TenantContext::isResolved()) {
            return Response::error('Contexto de tenant não resolvido.');
        }

        app(OperationContext::class)->set(OperationSource::MCP);

        $outcome = 'error';

        try {
            $result = $this->run($request, $user);
            $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

            if ($json === false || strlen($json) > self::MAX_RESPONSE_BYTES) {
                return Response::error('Resposta muito grande. Reduza per_page ou aplique filtros.');
            }

            $outcome = 'ok';

            return Response::structured($result === [] ? ['ok' => true] : $result);
        } catch (ValidationException $e) {
            $outcome = 'invalid';

            return Response::error('Dados inválidos: '.ValidationMessages::from($e));
        } catch (AuthorizationException $e) {
            $outcome = 'denied';

            return Response::error($e->status() === 404 ? self::NOT_FOUND : 'Ação não autorizada para o seu perfil.');
        } catch (ModelNotFoundException) {
            $outcome = 'denied';

            return Response::error(self::NOT_FOUND);
        } catch (HttpExceptionInterface $e) {
            $outcome = 'denied';

            return Response::error($e->getStatusCode() === 404 ? self::NOT_FOUND : 'Ação não autorizada para o seu perfil.');
        } catch (Throwable $e) {
            report($e);

            return Response::error('Erro interno ao executar a ferramenta.');
        } finally {
            $this->audit($user, $request, $outcome);
        }
    }

    private function audit(User $user, Request $request, string $outcome): void
    {
        try {
            app(AuditLogService::class)->recordEntity($user, AuditAction::McpToolCalled, 'mcp_tool', $this->name(), [
                'outcome' => $outcome,
                'arguments' => $this->summarize($request->all()),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Argumentos resumidos para auditoria (sem textos longos).
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function summarize(array $arguments): array
    {
        return collect($arguments)
            ->take(20)
            ->map(fn ($value) => is_string($value) ? Str::limit($value, 120) : (is_array($value) ? array_slice($value, 0, 20) : $value))
            ->all();
    }
}
