<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Order;
use App\Models\Template;
use App\Models\User;

/**
 * Формування документа (звіту) на основі замовлення і шаблону.
 * Файл не генерується: дані документа зберігаються у payload.
 */
class DocumentService
{
    public function generate(Template $template, Order $order, ?User $user, ?string $title = null): Document
    {
        $order->loadMissing(['counterparty', 'items.product']);

        $payload = $this->buildPayload($template, $order);

        $document = Document::query()->create([
            'number' => 'DOC-TMP-'.bin2hex(random_bytes(4)),
            'template_id' => $template->id,
            'counterparty_id' => $order->counterparty_id,
            'order_id' => $order->id,
            'user_id' => $user?->id,
            'title' => $title ?? $template->name.' до '.$order->number,
            'status' => 'draft',
            'payload' => $payload,
            'amount' => $order->total,
        ]);

        $document->number = sprintf('DOC-%08d', $document->id);
        $document->save();

        return $document;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(Template $template, Order $order): array
    {
        $lines = $order->items->map(fn ($item) => [
            'sku' => $item->product->sku,
            'name' => $item->product->name,
            'unit' => $item->product->unit,
            'quantity' => $item->quantity,
            'price' => (float) $item->price,
            'total' => (float) $item->total,
        ])->values()->all();

        $total = (float) $order->total;

        return [
            'template' => $template->code,
            'type' => $template->type,
            'order' => $order->number,
            'date' => $order->ordered_at?->toDateString(),
            'counterparty' => [
                'name' => $order->counterparty->name,
                'code' => $order->counterparty->code,
                'city' => $order->counterparty->city,
            ],
            'lines' => $lines,
            'total' => $total,
            'vat' => round($total / 6, 2),
        ];
    }
}
