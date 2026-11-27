<?php

namespace App\Services\Bench;

use App\Models\Order;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Сценарій 3: агреговані дані контрагента в кеші (Redis у робочому стенді).
 *
 * Читання: агрегати з кешу (read-through: при промаху рахуються з БД і кладуться в кеш).
 * Запис: лічильники переглядів і час останнього звернення.
 */
class CounterpartyStatsCache
{
    public const TTL = 3600;

    private function store(): Repository
    {
        return Cache::store();
    }

    public function key(int $counterpartyId): string
    {
        return "bench:cp:{$counterpartyId}:stats";
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(int $counterpartyId): array
    {
        return $this->store()->remember($this->key($counterpartyId), self::TTL, fn () => $this->compute($counterpartyId));
    }

    /**
     * Обчислити агрегати з БД (використовується при промаху кешу і для прогріву).
     *
     * @return array<string, mixed>
     */
    public function compute(int $counterpartyId): array
    {
        $byStatus = Order::query()
            ->where('counterparty_id', $counterpartyId)
            ->selectRaw('status, count(*) as cnt, coalesce(sum(total), 0) as amount')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->status => ['orders' => (int) $r->cnt, 'amount' => round((float) $r->amount, 2)]])
            ->all();

        return [
            'counterparty_id' => $counterpartyId,
            'by_status' => $byStatus,
            'orders' => array_sum(array_column($byStatus, 'orders')),
            'turnover' => round(array_sum(array_column($byStatus, 'amount')) - ($byStatus['cancelled']['amount'] ?? 0), 2),
        ];
    }

    public function warm(int $counterpartyId): void
    {
        $this->store()->put($this->key($counterpartyId), $this->compute($counterpartyId), self::TTL);
    }

    /**
     * Записати звернення: інкремент лічильників і оновлення агрегату.
     *
     * @return array<string, int>
     */
    public function hit(int $counterpartyId, string $channel): array
    {
        $store = $this->store();
        $views = (int) $store->increment("bench:cp:{$counterpartyId}:views");
        $channelViews = (int) $store->increment("bench:cp:{$counterpartyId}:views:{$channel}");
        $store->put("bench:cp:{$counterpartyId}:last_hit", time(), self::TTL);
        $total = (int) $store->increment('bench:views:total');

        return ['views' => $views, 'channel_views' => $channelViews, 'total' => $total];
    }
}
