<?php

namespace App\Modules\Mcp\Services;

use App\Modules\Mcp\Http\Middleware\EnsureMcpAccess;
use App\Modules\User\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Tokens pessoais (Sanctum) que identificam o usuário numa conexão MCP.
 * Cada token pertence a um único usuário — nunca há identidade compartilhada.
 */
class McpTokenService
{
    public const PREFIX = 'mcp:';

    public static function isMcpToken(PersonalAccessToken $token): bool
    {
        return $token->abilities === [EnsureMcpAccess::ABILITY];
    }

    /**
     * @return Collection<int, PersonalAccessToken>
     */
    public function list(User $user): Collection
    {
        return $user->tokens()
            ->where('name', 'like', self::PREFIX.'%')
            ->latest()
            ->get()
            ->filter(fn (PersonalAccessToken $token) => self::isMcpToken($token))
            ->values();
    }

    public function issue(User $user, string $name, ?int $expiresInDays = null): NewAccessToken
    {
        return $user->createToken(
            self::PREFIX.$name,
            [EnsureMcpAccess::ABILITY],
            $expiresInDays ? Carbon::now()->addDays($expiresInDays) : null,
        );
    }

    public function revoke(User $user, int $tokenId): bool
    {
        return (bool) $user->tokens()
            ->whereKey($tokenId)
            ->where('name', 'like', self::PREFIX.'%')
            ->delete();
    }
}
