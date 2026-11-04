<?php

namespace App\Http\Requests\Api;

use App\Models\Counterparty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CounterpartyRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('counterparty')?->id;
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'code' => [$required, 'string', 'regex:/^\d{8}(\d{2})?$/', Rule::unique('counterparties', 'code')->ignore($id)],
            'type' => [$required, Rule::in(Counterparty::TYPES)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'city' => [$required, 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
