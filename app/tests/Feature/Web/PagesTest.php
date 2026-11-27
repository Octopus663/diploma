<?php

namespace Tests\Feature\Web;

use App\Models\Document;
use App\Models\Order;
use App\Models\Product;
use App\Models\Template;
use App\Models\User;
use App\Services\BenchmarkResults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/orders')->assertRedirect('/login');
    }

    public function test_public_pages_are_available(): void
    {
        $this->get('/benchmarks')->assertOk();
        $this->get('/about')->assertOk()->assertSee('PHP SAPI');
    }

    public function test_authenticated_pages_render(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();
        $order->items()->create(['product_id' => Product::factory()->create()->id, 'quantity' => 2, 'price' => 10, 'total' => 20]);
        $template = Template::factory()->create();
        $document = Document::factory()->create(['order_id' => $order->id, 'payload' => [
            'lines' => [['sku' => 'S1', 'name' => 'Товар', 'unit' => 'шт', 'quantity' => 2, 'price' => 10, 'total' => 20]],
            'total' => 20, 'vat' => 3.33,
        ]]);

        $this->actingAs($user);

        foreach (['/dashboard', '/counterparties', "/counterparties/{$order->counterparty_id}", '/orders', "/orders/{$order->id}", '/documents', "/documents/{$document->id}", '/templates'] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get("/documents/{$document->id}/export.json")->assertOk()->assertJsonPath('total', 20);
        $csv = $this->get("/documents/{$document->id}/export.csv")->assertOk()->streamedContent();
        $this->assertStringContainsString('Товар', $csv);

        $this->post("/orders/{$order->id}/documents", ['template_id' => $template->id])
            ->assertRedirect();
        $this->assertSame(2, Document::query()->count());
    }

    public function test_benchmark_results_are_parsed(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bench');
        file_put_contents($path, "mode,scenario,vus,rps,p50_ms,p95_ms,p99_ms,error_rate,cpu_pct,mem_mb,runs\n"
            ."fpm,ping,10,100,5,9,12,0,99,40,3\n"
            ."swoole,ping,10,400,2,4,6,0,98,120,3\n");

        $results = new BenchmarkResults($path);

        $this->assertSame(['ping'], $results->scenarios());
        $this->assertSame(300.0, $results->peakTable()[0]['swoole_gain']);
        $this->assertCount(2, $results->lineChart('ping', 'rps', 't')['data']['datasets']);

        unlink($path);
    }
}
