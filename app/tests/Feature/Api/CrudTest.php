<?php

namespace Tests\Feature\Api;

use App\Models\Counterparty;
use App\Models\Order;
use App\Models\Product;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_counterparty_crud(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $id = $this->postJson('/api/counterparties', [
            'name' => 'ТОВ Тест', 'code' => '12345678', 'type' => 'company', 'city' => 'Київ',
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/counterparties/{$id}")->assertOk()->assertJsonPath('data.code', '12345678');
        $this->patchJson("/api/counterparties/{$id}", ['city' => 'Львів'])->assertOk()->assertJsonPath('data.city', 'Львів');
        $this->getJson('/api/counterparties?q=Тест')->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson("/api/counterparties/{$id}")->assertNoContent();
    }

    public function test_order_is_created_with_items_and_total(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $counterparty = Counterparty::factory()->create();
        $a = Product::factory()->create(['price' => 100]);
        $b = Product::factory()->create(['price' => 2.5]);

        $response = $this->postJson('/api/orders', [
            'counterparty_id' => $counterparty->id,
            'ordered_at' => '2026-10-01',
            'items' => [
                ['product_id' => $a->id, 'quantity' => 2],
                ['product_id' => $b->id, 'quantity' => 4],
            ],
        ])->assertCreated();

        $response->assertJsonPath('data.total', 210)->assertJsonCount(2, 'data.items');
        $this->assertMatchesRegularExpression('/^ORD-\d{8}$/', $response->json('data.number'));
    }

    public function test_manager_cannot_delete(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $order = Order::factory()->create();

        $this->deleteJson("/api/orders/{$order->id}")->assertForbidden();
    }

    public function test_document_is_generated_from_order(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $template = Template::factory()->create(['type' => 'invoice']);
        $order = Order::factory()->create(['total' => 600]);
        $order->items()->create(['product_id' => Product::factory()->create()->id, 'quantity' => 1, 'price' => 600, 'total' => 600]);

        $this->postJson('/api/documents', ['template_id' => $template->id, 'order_id' => $order->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.payload.total', 600)
            ->assertJsonPath('data.payload.vat', 100)
            ->assertJsonCount(1, 'data.payload.lines');
    }
}
