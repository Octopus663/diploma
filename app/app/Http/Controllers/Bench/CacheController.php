<?php

namespace App\Http\Controllers\Bench;

use App\Http\Controllers\Controller;
use App\Services\Bench\CounterpartyStatsCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Сценарій 3: читання та запис агрегованих даних у Redis.
 */
class CacheController extends Controller
{
    public function __construct(private readonly CounterpartyStatsCache $cache) {}

    public function show(int $counterparty): JsonResponse
    {
        return response()->json($this->cache->stats($counterparty));
    }

    public function hit(Request $request, int $counterparty): JsonResponse
    {
        $channel = $request->validate(['channel' => ['sometimes', 'in:web,api,mobile']])['channel'] ?? 'api';

        return response()->json([
            'counters' => $this->cache->hit($counterparty, $channel),
            'stats' => $this->cache->stats($counterparty),
        ]);
    }
}
