<?php

namespace App\Modules\Workspace\Support;

use App\Modules\Workspace\Enums\OperationSource;

/**
 * Origem da operação corrente (escopo de requisição).
 *
 * O servidor MCP marca explicitamente MCP; caso contrário a origem é
 * inferida: sessão de navegador = UI, token pessoal = API.
 */
final class OperationContext
{
    private ?OperationSource $source = null;

    public function set(OperationSource $source): void
    {
        $this->source = $source;
    }

    public function source(): OperationSource
    {
        if ($this->source !== null) {
            return $this->source;
        }

        $request = request();

        return $request->hasSession() && ! $request->bearerToken()
            ? OperationSource::UI
            : OperationSource::API;
    }
}
