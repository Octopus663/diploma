<?php

namespace App\Http\Requests\Api;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderRequest extends FormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'counterparty_id' => [$required, 'integer', 'exists:counterparties,id'],
            'status' => ['sometimes', Rule::in(Order::STATUSES)],
            'ordered_at' => [$required, 'date'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => [$required, 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required_with:items', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
