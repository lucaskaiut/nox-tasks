<?php

namespace App\Modules\Workspace\Policies\Concerns;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Regras comuns às Policies do Workspace.
 *
 * - Recurso fora do tenant ou do escopo do usuário => 404 (não revela existência).
 * - Recurso visível, mas sem permissão para a ação => 403.
 * - Ações de gestão exigem perfil profissional (usuário sem vínculo com cliente)
 *   além da permissão — um papel mal configurado não amplia o acesso de clientes.
 */
trait AuthorizesWorkspace
{
    /** Mesma mensagem de um recurso inexistente: não revela existência. */
    private const NOT_FOUND = 'Recurso não encontrado.';

    protected function inTenant(Model $model): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $model->getAttribute('tenant_id'));
    }

    protected function professional(User $user, Permission $permission): bool
    {
        return ! $user->isClientUser() && $user->hasPermission($permission);
    }

    protected function readable(bool $visible): Response
    {
        return $visible ? Response::allow() : Response::denyAsNotFound(self::NOT_FOUND);
    }

    /**
     * Primeiro garante a visibilidade (404), depois a permissão (403).
     */
    protected function act(bool $visible, bool $allowed): Response
    {
        if (! $visible) {
            return Response::denyAsNotFound(self::NOT_FOUND);
        }

        return $allowed ? Response::allow() : Response::deny();
    }
}
