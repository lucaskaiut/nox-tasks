<?php

namespace App\Modules\Mcp\Http\Middleware;

use App\Modules\Mcp\Services\McpTokenService;
use App\Modules\Shared\Http\ApiError;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tokens emitidos para MCP valem apenas no servidor MCP (que não está no
 * grupo "api"). Impede que um token entregue a um agente seja usado contra
 * o restante da API REST.
 */
class RestrictMcpTokens
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if ($bearer !== null && str_contains($bearer, '|')) {
            $token = PersonalAccessToken::findToken($bearer);

            if ($token !== null && McpTokenService::isMcpToken($token)) {
                return ApiError::response('Este token só pode ser usado no servidor MCP.', 403);
            }
        }

        return $next($request);
    }
}
