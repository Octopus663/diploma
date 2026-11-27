<?php

namespace App\Http\Controllers\Bench;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Пам'ять процесу, що обробив запит (для графіків «пам'ять воркера від кількості запитів»).
 * Доступний лише при BENCH_DEBUG=true або BENCH_LEAK_DEMO=true.
 */
class DebugController extends Controller
{
    public function memory(): JsonResponse
    {
        return response()->json([
            'pid' => getmypid(),
            'memory_mb' => round(memory_get_usage() / 1048576, 2),
            'memory_real_mb' => round(memory_get_usage(true) / 1048576, 2),
            'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
            'sapi' => PHP_SAPI,
        ]);
    }
}
