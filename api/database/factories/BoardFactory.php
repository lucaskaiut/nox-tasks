<?php

namespace Database\Factories;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Workspace\Enums\ColumnKind;
use App\Modules\Workspace\Models\Board;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Board>
 */
class BoardFactory extends Factory
{
    protected $model = Board::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(2, true),
        ];
    }

    /**
     * Cria as colunas: A fazer (open), Em andamento (open), Aguardando (waiting), Concluído (done), Cancelado (cancelled).
     */
    public function withColumns(): static
    {
        return $this->afterCreating(function (Board $board): void {
            $columns = [
                ['A fazer', ColumnKind::OPEN],
                ['Em andamento', ColumnKind::OPEN],
                ['Aguardando', ColumnKind::WAITING],
                ['Concluído', ColumnKind::DONE],
                ['Cancelado', ColumnKind::CANCELLED],
            ];

            foreach ($columns as $position => [$name, $kind]) {
                $board->columns()->forceCreate([
                    'tenant_id' => $board->tenant_id,
                    'name' => $name,
                    'kind' => $kind,
                    'position' => $position,
                    'color' => '#71717a',
                ]);
            }
        });
    }
}
