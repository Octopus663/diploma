<?php

namespace Database\Factories;

use App\Models\Counterparty;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->bothify('ORD-########'),
            'counterparty_id' => Counterparty::factory(),
            'status' => fake()->randomElement(Order::STATUSES),
            'ordered_at' => fake()->dateTimeBetween('-1 year'),
            'total' => 0,
            'currency' => 'UAH',
        ];
    }
}
