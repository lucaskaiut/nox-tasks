<?php

namespace App\Modules\Mcp\Http\Controllers;

use App\Modules\Mcp\Services\McpTokenService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * "Conexões de IA": o próprio usuário emite e revoga seus tokens MCP.
 */
class McpTokenController extends ApiController
{
    public function __construct(private readonly McpTokenService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->success([
            'server_url' => url('/mcp/workspace'),
            'tokens' => $this->service->list($request->user())->map(fn (PersonalAccessToken $token) => [
                'id' => $token->getKey(),
                'name' => substr($token->name, strlen(McpTokenService::PREFIX)),
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'expires_in_days' => ['nullable', 'integer', 'in:7,30,90,365'],
        ]);

        $token = $this->service->issue($request->user(), $data['name'], $data['expires_in_days'] ?? null);

        return $this->created([
            'id' => $token->accessToken->getKey(),
            'name' => $data['name'],
            'token' => $token->plainTextToken,
            'server_url' => url('/mcp/workspace'),
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
        ], 'Token MCP criado. Copie-o agora: ele não será exibido novamente.');
    }

    public function destroy(Request $request, int $token): JsonResponse
    {
        abort_unless($this->service->revoke($request->user(), $token), 404, 'Recurso não encontrado.');

        return $this->success(null, 'Token revogado.');
    }
}
