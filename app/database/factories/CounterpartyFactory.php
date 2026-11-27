<?php

namespace Database\Factories;

use App\Models\Counterparty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Counterparty>
 */
class CounterpartyFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(Counterparty::TYPES);

        return [
            'name' => $type === 'company' ? fake()->company() : fake()->name(),
            'code' => fake()->unique()->numerify($type === 'company' ? '########' : '##########'),
            'type' => $type,
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('+380#########'),
            'city' => fake()->city(),
            'address' => fake()->streetAddress(),
        ];
    }
}
