<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sku' => fake()->unique()->bothify('SKU-#####'),
            'name' => fake()->words(3, true),
            'category' => fake()->randomElement(['services', 'hardware', 'software', 'consulting']),
            'unit' => fake()->randomElement(['шт', 'год', 'посл']),
            'price' => fake()->randomFloat(2, 10, 5000),
        ];
    }
}
