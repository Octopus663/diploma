<?php

namespace Tests\Feature\LeakDemo;

use Tests\TestCase;

class LeakDemoDisabledTest extends TestCase
{
    public function test_leak_routes_are_absent_by_default(): void
    {
        $this->getJson('/api/leak/static')->assertNotFound();
        $this->getJson('/api/_debug/memory')->assertNotFound();
    }
}
