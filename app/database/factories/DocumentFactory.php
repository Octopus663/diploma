<?php

namespace Database\Factories;

use App\Models\Counterparty;
use App\Models\Document;
use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->bothify('DOC-########'),
            'template_id' => Template::factory(),
            'counterparty_id' => Counterparty::factory(),
            'title' => fake()->sentence(4),
            'status' => fake()->randomElement(Document::STATUSES),
            'payload' => ['lines' => [], 'total' => 0],
            'amount' => fake()->randomFloat(2, 100, 100000),
            'issued_at' => now(),
        ];
    }
}
