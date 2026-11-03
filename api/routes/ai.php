<?php

use App\Modules\Mcp\Servers\WorkspaceServer;
use Laravel\Mcp\Facades\Mcp;

/*
 * Servidor MCP do Workspace. Autenticação via:
 * - token pessoal Sanctum com a ability "mcp" (emitido pelo próprio usuário
 *   em "Conexões de IA");
 * - access token OAuth 2.1 do Passport (guard "api", escopo mcp:use), usado
 *   por conectores remotos como o claude.ai. Descoberta pela spec MCP:
 *   401 com WWW-Authenticate -> /.well-known/oauth-protected-resource ->
 *   /.well-known/oauth-authorization-server -> registro dinâmico de cliente
 *   (/oauth/register) -> /oauth/authorize (login + consentimento) -> /oauth/token.
 * Ordem: autentica → resolve tenant (mesma lógica da API) → valida acesso MCP
 * → assinatura ativa → rate limit por usuário.
 */
Mcp::oauthRoutes();

Mcp::web('/mcp/workspace', WorkspaceServer::class)
    ->middleware(['auth:sanctum,api', 'tenant', 'mcp.access', 'subscription.active', 'throttle:mcp']);
