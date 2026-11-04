<?php

namespace App\Services\Bench;

use App\Models\Counterparty;
use App\Models\Order;

/**
 * Сценарій 2: складні вибірки зв'язаних даних через Eloquent ORM.
 */
class ReportQuery
{
    /**
     * Звіт по замовленню: замовлення, контрагент, позиції з товарами, документи з шаблонами
     * і агрегати по контрагенту.
     *
     * @return array<string, mixed>
     */
    public function orderReport(int $orderId): array
    {
        $order = Order::query()
            ->with(['counterparty', 'items.product', 'documents.template'])
            ->findOrFail($orderId);

        $counterpartyStats = Order::query()
            ->where('counterparty_id', $order->counterparty_id)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('count(*) as orders, coalesce(sum(total), 0) as turnover, max(ordered_at) as last_order')
            ->first();

        return [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status,
                'date' => $order->ordered_at->toDateString(),
                'total' => (float) $order->total,
            ],
            'counterparty' => [
                'id' => $order->counterparty->id,
                'name' => $order->counterparty->name,
                'code' => $order->counterparty->code,
                'orders' => (int) $counterpartyStats->orders,
                'turnover' => round((float) $counterpartyStats->turnover, 2),
                'last_order' => substr((string) $counterpartyStats->last_order, 0, 10),
            ],
            'items' => $order->items->sortBy('id')->map(fn ($item) => [
                'sku' => $item->product->sku,
                'name' => $item->product->name,
                'category' => $item->product->category,
                'quantity' => $item->quantity,
                'price' => (float) $item->price,
                'total' => (float) $item->total,
            ])->values()->all(),
            'documents' => $order->documents->sortBy('id')->map(fn ($doc) => [
                'number' => $doc->number,
                'template' => $doc->template->code,
                'status' => $doc->status,
            ])->values()->all(),
        ];
    }

    /**
     * Останні замовлення контрагента з позиціями та документами.
     *
     * @return array<string, mixed>
     */
    public function counterpartyOrders(int $counterpartyId, int $limit): array
    {
        $counterparty = Counterparty::query()->findOrFail($counterpartyId);

        $orders = $counterparty->orders()
            ->with(['items.product:id,sku,name', 'documents:id,order_id,number,status'])
            ->orderByDesc('ordered_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return [
            'counterparty' => ['id' => $counterparty->id, 'name' => $counterparty->name],
            'orders' => $orders->map(fn (Order $order) => [
                'number' => $order->number,
                'date' => $order->ordered_at->toDateString(),
                'status' => $order->status,
                'total' => (float) $order->total,
                'items' => $order->items->sortBy('id')->map(fn ($i) => [$i->product->sku, $i->quantity, (float) $i->total])->values()->all(),
                'documents' => $order->documents->sortBy('id')->pluck('number')->values()->all(),
            ])->all(),
        ];
    }
}
