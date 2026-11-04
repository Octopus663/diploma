<?php

namespace App\Models;

use Database\Factories\CounterpartyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Контрагент (юридична або фізична особа), з яким ведуться замовлення та документи.
 */
#[Fillable(['name', 'code', 'type', 'email', 'phone', 'city', 'address'])]
class Counterparty extends Model
{
    /** @use HasFactory<CounterpartyFactory> */
    use HasFactory;

    public const TYPES = ['company', 'individual'];

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
