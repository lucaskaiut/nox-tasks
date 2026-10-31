<?php

namespace App\Modules\Workspace\Services;

use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Profissionais do tenant (usuários sem vínculo com cliente) que podem ser
 * responsáveis ou participantes de projetos e tarefas.
 */
class MemberService
{
    public function paginate(int $perPage = 50, ?string $search = null): LengthAwarePaginator
    {
        return $this->professionals()
            ->when(filled($search), function (Builder $query) use ($search): void {
                $query->where(fn (Builder $q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  list<string>  $uuids
     * @return Collection<int, User>
     */
    public function findProfessionals(array $uuids): Collection
    {
        if ($uuids === []) {
            return new Collection;
        }

        return $this->professionals()->whereIn('uuid', array_values(array_unique($uuids)))->get();
    }

    public function findProfessional(?string $uuid): ?User
    {
        return $uuid === null ? null : $this->professionals()->where('uuid', $uuid)->first();
    }

    /**
     * @return Builder<User>
     */
    public function professionals(): Builder
    {
        return User::query()->whereNull('client_id');
    }
}
