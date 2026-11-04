<?php

namespace Database\Factories;

use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Template>
 */
class TemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('TPL-####'),
            'name' => fake()->sentence(3),
            'type' => fake()->randomElement(Template::TYPES),
            'description' => fake()->sentence(),
            'fields' => ['number', 'date', 'counterparty', 'items', 'total'],
            'is_active' => true,
        ];
    }
}
