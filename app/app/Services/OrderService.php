<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Створення та оновлення замовлень з позиціями.
 * Сервіс без стану: безпечний для довгоживучих воркерів Octane.
 */
class OrderService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $user): Order
    {
        return DB::transaction(function () use ($data, $user) {
            $order = Order::query()->create([
                'number' => 'ORD-TMP-'.bin2hex(random_bytes(4)),
                'counterparty_id' => $data['counterparty_id'],
                'user_id' => $user?->id,
                'status' => $data['status'] ?? 'new',
                'ordered_at' => $data['ordered_at'],
                'currency' => $data['currency'] ?? 'UAH',
                'notes' => $data['notes'] ?? null,
                'total' => 0,
            ]);

            $order->number = sprintf('ORD-%08d', $order->id);
            $this->syncItems($order, $data['items']);

            return $order;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data) {
            $order->fill(collect($data)->except('items')->all());

            if (isset($data['items'])) {
                $order->items()->delete();
                $this->syncItems($order, $data['items']);
            } else {
                $order->save();
            }

            return $order;
        });
    }

    /**
     * @param  array<int, array{product_id:int, quantity:int}>  $items
     */
    private function syncItems(Order $order, array $items): void
    {
        $prices = Product::query()
            ->whereIn('id', array_column($items, 'product_id'))
            ->pluck('price', 'id');

        $total = 0.0;
        $rows = [];

        foreach ($items as $item) {
            $price = (float) $prices[$item['product_id']];
            $lineTotal = round($price * $item['quantity'], 2);
            $total += $lineTotal;
            $rows[] = [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $price,
                'total' => $lineTotal,
            ];
        }

        $order->items()->createMany($rows);
        $order->total = round($total, 2);
        $order->save();
    }
}
