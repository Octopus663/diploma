<?php

namespace Tests\Feature\LeakDemo;

use App\LeakDemo\StaticCollector;
use Tests\TestCase;

/**
 * Тести відтворюють поведінку довгоживучого воркера: у межах одного тесту застосунок
 * завантажується один раз і обробляє кілька запитів, як воркер Octane.
 */
class LeakDemoTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('BENCH_LEAK_DEMO=true');
        $_ENV['BENCH_LEAK_DEMO'] = $_SERVER['BENCH_LEAK_DEMO'] = 'true';
        StaticCollector::$events = [];

        parent::setUp();
    }

    protected function tearDown(): void
    {
        putenv('BENCH_LEAK_DEMO');
        unset($_ENV['BENCH_LEAK_DEMO'], $_SERVER['BENCH_LEAK_DEMO']);
        StaticCollector::$events = [];

        parent::tearDown();
    }

    public function test_static_array_grows_between_requests(): void
    {
        $this->getJson('/api/leak/static')->assertJsonPath('stored_events', 1);
        $this->getJson('/api/leak/static')->assertJsonPath('stored_events', 2);
        $this->getJson('/api/leak/static')->assertJsonPath('stored_events', 3);

        $this->getJson('/api/leak/fixed/static')->assertJsonPath('stored_events', 1);
        $this->getJson('/api/leak/fixed/static')->assertJsonPath('stored_events', 1);
    }

    public function test_singleton_with_request_leaks_state_between_requests(): void
    {
        // Синглтон створено в boot() з іншим запитом: ім'я з поточного запиту він не бачить.
        $this->getJson('/api/leak/singleton?name=alice')->assertJsonMissing(['name' => 'alice']);
        $this->getJson('/api/leak/singleton?name=bob')->assertJsonMissing(['name' => 'bob']);

        // scoped-прив'язка отримує актуальний запит. Octane скидає scoped-екземпляри
        // після кожного запиту; тут це робиться вручну.
        $this->getJson('/api/leak/fixed/singleton?name=alice')->assertJsonPath('name', 'alice');
        $this->app->forgetScopedInstances();
        $this->getJson('/api/leak/fixed/singleton?name=bob')->assertJsonPath('name', 'bob');
    }

    public function test_unbounded_memo_grows_and_bounded_does_not(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $leak = $this->getJson("/api/leak/memo?key=k{$i}")->json('memo_size');
            $fixed = $this->getJson("/api/leak/fixed/memo?key=k{$i}")->json('memo_size');
        }

        $this->assertSame(120, $leak);
        $this->assertSame(100, $fixed);
    }

    public function test_debug_memory_endpoint(): void
    {
        $this->getJson('/api/_debug/memory')->assertOk()->assertJsonStructure(['pid', 'memory_mb', 'peak_mb']);
    }
}
