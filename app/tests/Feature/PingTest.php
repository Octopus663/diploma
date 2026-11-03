<?php

namespace Tests\Feature;

use Tests\TestCase;

class PingTest extends TestCase
{
    public function test_ping_returns_static_json(): void
    {
        $this->getJson('/api/ping')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'app' => config('app.name'),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
            ]);
    }
}
