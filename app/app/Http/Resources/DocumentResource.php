<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Document */
class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'title' => $this->title,
            'status' => $this->status,
            'amount' => $this->amount === null ? null : (float) $this->amount,
            'payload' => $this->payload,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'template_id' => $this->template_id,
            'counterparty_id' => $this->counterparty_id,
            'order_id' => $this->order_id,
            'template' => new TemplateResource($this->whenLoaded('template')),
            'counterparty' => new CounterpartyResource($this->whenLoaded('counterparty')),
        ];
    }
}
