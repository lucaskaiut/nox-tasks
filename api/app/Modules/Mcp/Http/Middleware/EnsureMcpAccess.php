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
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\AccessToken as OAuthAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige um token pessoal (Sanctum) emitido exclusivamente para MCP (abilities
 * exatamente ["mcp"]; tokens de login com "*" são recusados) —
 * pertencente a um usuário com a permissão mcp.use — ou um access token
 * OAuth 2.1 (Passport, escopo mcp:use) de conector remoto. Sessões de navegador e
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

        if (! $user instanceof User || ! $this->isMcpToken($token)) {
            return ApiError::response('Token MCP inválido.', 401);
        }

        if (! $user->hasPermission(Permission::MCP_USE)) {
            return ApiError::response('Seu perfil não permite conexões MCP.', 403);
        }

        $this->context->set(OperationSource::MCP);

        return $next($request);
    }

    /**
     * Token pessoal Sanctum com ability exatamente ["mcp"] ou access token
     * OAuth (Passport) com o escopo "mcp:use" — emitido para conectores
     * remotos (claude.ai) após login + consentimento do usuário.
     */
    private function isMcpToken(mixed $token): bool
    {
        if ($token instanceof PersonalAccessToken) {
            return McpTokenService::isMcpToken($token);
        }

        return $token instanceof OAuthAccessToken && $token->can(Registrar::OAUTH_SCOPE);
    }
}
