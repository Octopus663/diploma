<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'ordered_at' => $this->ordered_at?->toDateString(),
            'total' => (float) $this->total,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'counterparty_id' => $this->counterparty_id,
            'counterparty' => new CounterpartyResource($this->whenLoaded('counterparty')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
            'items_count' => $this->whenCounted('items'),
        ];
    }
}
