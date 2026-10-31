<?php

use App\Modules\Mcp\Servers\WorkspaceServer;
use Laravel\Mcp\Facades\Mcp;

/*
 * Servidor MCP do Workspace. Autenticação via token pessoal Sanctum com a
 * ability "mcp" (emitido pelo próprio usuário em "Conexões de IA").
 * Ordem: autentica → resolve tenant (mesma lógica da API) → valida acesso MCP
 * → assinatura ativa → rate limit por usuário.
 */
Mcp::web('/mcp/workspace', WorkspaceServer::class)
    ->middleware(['auth:sanctum', 'tenant', 'mcp.access', 'subscription.active', 'throttle:mcp']);
