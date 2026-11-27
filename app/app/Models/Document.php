<?php

namespace App\Models;

use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Цифровий документ або звіт. Файл (PDF/DOCX) не генерується:
 * дані зберігаються в payload і можуть бути експортовані в JSON/CSV.
 */
#[Fillable(['number', 'template_id', 'counterparty_id', 'order_id', 'user_id', 'title', 'status', 'payload', 'amount', 'issued_at'])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    public const STATUSES = ['draft', 'issued', 'signed', 'cancelled'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'amount' => 'decimal:2',
            'issued_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Template, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /** @return BelongsTo<Counterparty, $this> */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
