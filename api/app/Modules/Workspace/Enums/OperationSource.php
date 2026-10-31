<?php

namespace App\Modules\Workspace\Enums;

/**
 * Origem de uma operação: interface web (sessão), API (token pessoal)
 * ou agente de IA conectado via MCP.
 */
enum OperationSource: string
{
    case UI = 'ui';
    case API = 'api';
    case MCP = 'mcp';
}
