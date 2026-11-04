<?php

namespace Tests\Feature\Bench;

use App\Models\Counterparty;
use App\Models\Document;
use App\Models\Order;
use App\Models\Product;
use App\Models\Template;
use App\Models\User;
use App\Services\Bench\ReportCompute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BenchScenariosTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());

        $counterparty = Counterparty::factory()->create();
        $this->order = Order::factory()->for($counterparty)->create(['status' => 'completed', 'total' => 150]);
        $product = Product::factory()->create(['sku' => 'SKU-1']);
        $this->order->items()->create(['product_id' => $product->id, 'quantity' => 3, 'price' => 50, 'total' => 150]);
        Document::factory()->create(['order_id' => $this->order->id, 'counterparty_id' => $counterparty->id, 'template_id' => Template::factory()]);
        Order::factory()->for($counterparty)->create(['status' => 'cancelled', 'total' => 999]);
    }

    public function test_bench_routes_require_token(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/bench/db/orders/1', ['Authorization' => 'Bearer invalid'])->assertUnauthorized();
    }

    public function test_order_report(): void
    {
        $this->getJson("/api/bench/db/orders/{$this->order->id}")
            ->assertOk()
            ->assertJsonPath('order.total', 150)
            ->assertJsonPath('counterparty.orders', 1)
            ->assertJsonPath('counterparty.turnover', 150)
            ->assertJsonPath('items.0.sku', 'SKU-1')
            ->assertJsonCount(1, 'documents');

        $this->getJson('/api/bench/db/orders/999999')->assertNotFound();
    }

    public function test_counterparty_orders(): void
    {
        $this->getJson("/api/bench/db/counterparties/{$this->order->counterparty_id}/orders?limit=5")
            ->assertOk()
            ->assertJsonCount(2, 'orders');
    }

    public function test_cache_read_and_write(): void
    {
        $id = $this->order->counterparty_id;

        $this->getJson("/api/bench/cache/counterparties/{$id}")
            ->assertOk()
            ->assertJsonPath('orders', 2)
            ->assertJsonPath('turnover', 150);

        $this->postJson("/api/bench/cache/counterparties/{$id}/hit")->assertOk()->assertJsonPath('counters.views', 1);
        $this->postJson("/api/bench/cache/counterparties/{$id}/hit", ['channel' => 'web'])
            ->assertOk()
            ->assertJsonPath('counters.views', 2)
            ->assertJsonPath('counters.channel_views', 1);

        $this->artisan('bench:warm-cache')->assertSuccessful();
    }

    public function test_compute_is_deterministic_and_bounded(): void
    {
        $a = $this->getJson('/api/bench/compute/report?rows=2000&seed=7')->assertOk()->json();
        $b = $this->postJson('/api/bench/compute/report', ['rows' => 2000, 'seed' => 7])->assertOk()->json();

        $this->assertSame($a['checksum'], $b['checksum']);
        $this->assertSame(2000, $a['rows']);
        $this->assertSame(2000, array_sum(array_column($a['by_category'], 'count')));
        $this->assertGreaterThanOrEqual($a['p50'], $a['p95']);

        $this->getJson('/api/bench/compute/report?rows=1000000')->assertUnprocessable();
        $this->assertArrayHasKey('data', $this->getJson('/api/bench/compute/report?rows=10&include_rows=1')->json());
    }

    public function test_compute_does_not_depend_on_global_random_state(): void
    {
        $service = new ReportCompute;
        $first = $service->run(500, 1, false)['checksum'];
        mt_srand(123);
        mt_rand();
        $this->assertSame($first, $service->run(500, 1, false)['checksum']);
    }
}
