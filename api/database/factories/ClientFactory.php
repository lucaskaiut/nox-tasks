<?php

namespace Database\Factories;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Workspace\Enums\ClientStatus;
use App\Modules\Workspace\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->numerify('419########'),
            'status' => ClientStatus::ACTIVE,
        ];
    }
}
