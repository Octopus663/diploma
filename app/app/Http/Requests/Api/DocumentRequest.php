<?php

namespace App\Http\Requests\Api;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentRequest extends FormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'template_id' => [$required, 'integer', 'exists:templates,id'],
            'order_id' => [$required, 'integer', 'exists:orders,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(Document::STATUSES)],
        ];
    }
}
