<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Замовлення контрагента з позиціями.
 */
#[Fillable(['number', 'counterparty_id', 'user_id', 'status', 'ordered_at', 'total', 'currency', 'notes'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    public const STATUSES = ['new', 'processing', 'completed', 'cancelled'];

    protected function casts(): array
    {
        return [
            'ordered_at' => 'date',
            'total' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Counterparty, $this> */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Перерахувати суму замовлення за позиціями.
     */
    public function recalculateTotal(): void
    {
        $this->total = $this->items()->sum('total');
        $this->save();
    }
}
