<?php

namespace Database\Factories;

use App\Modules\Workspace\Enums\ProjectStatus;
use App\Modules\Workspace\Models\Client;
use App\Modules\Workspace\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->catchPhrase(),
            'description' => fake()->sentence(),
            'status' => ProjectStatus::ACTIVE,
            'internal_notes' => 'Nota interna: margem apertada.',
        ];
    }

    /**
     * Garante também o quadro geral do tenant, onde ficam as tarefas.
     */
    public function forClient(Client $client): static
    {
        return $this->state(function () use ($client): array {
            BoardFactory::generalFor((int) $client->tenant_id);

            return [
                'tenant_id' => $client->tenant_id,
                'client_id' => $client->getKey(),
            ];
        });
    }
}
