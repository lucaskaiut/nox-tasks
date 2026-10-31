<?php

namespace App\Modules\Mcp\Http\Middleware;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Mcp\Services\McpTokenService;
use App\Modules\Shared\Http\ApiError;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Enums\OperationSource;
use App\Modules\Workspace\Support\OperationContext;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige um token pessoal (Sanctum) emitido exclusivamente para MCP (abilities
 * exatamente ["mcp"]; tokens de login com "*" são recusados) —
 * pertencente a um usuário com a permissão mcp.use. Sessões de navegador e
 * tokens de login comuns não abrem conexões MCP.
 */
class EnsureMcpAccess
{
    public const ABILITY = 'mcp';

    public function __construct(private readonly OperationContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (! $user instanceof User || ! $token instanceof PersonalAccessToken || ! McpTokenService::isMcpToken($token)) {
            return ApiError::response('Token MCP inválido.', 401);
        }

        if (! $user->hasPermission(Permission::MCP_USE)) {
            return ApiError::response('Seu perfil não permite conexões MCP.', 403);
        }

        $this->context->set(OperationSource::MCP);

        return $next($request);
    }
}
