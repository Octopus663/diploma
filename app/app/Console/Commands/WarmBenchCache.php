<?php

namespace App\Console\Commands;

use App\Models\Counterparty;
use App\Services\Bench\CounterpartyStatsCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Прогрів кешу для сценарію 3: агрегати всіх контрагентів кладуться в Redis заздалегідь,
 * щоб під час вимірювань читання не йшли в БД.
 */
class WarmBenchCache extends Command
{
    protected $signature = 'bench:warm-cache {--flush : Спершу очистити кеш}';

    protected $description = 'Заповнити кеш агрегатами контрагентів для бенчмарку';

    public function handle(CounterpartyStatsCache $cache): int
    {
        if ($this->option('flush')) {
            Cache::flush();
        }

        $count = 0;

        Counterparty::query()->select('id')->orderBy('id')->chunkById(500, function ($chunk) use ($cache, &$count) {
            foreach ($chunk as $counterparty) {
                $cache->warm($counterparty->id);
                $count++;
            }
        });

        $this->info("Прогріто: {$count}");

        return self::SUCCESS;
    }
}
