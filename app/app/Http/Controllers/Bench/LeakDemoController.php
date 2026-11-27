<?php

namespace App\Http\Controllers\Bench;

use App\Http\Controllers\Controller;
use App\LeakDemo\BoundedReportMemo;
use App\LeakDemo\ReportMemo;
use App\LeakDemo\StaticCollector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Демонстрація витоків пам'яті і стану в Octane та їх виправлень (BENCH_LEAK_DEMO=true).
 */
class LeakDemoController extends Controller
{
    // ---- 1. Статичний масив ----

    public function staticLeak(Request $request): JsonResponse
    {
        $count = StaticCollector::record($this->event($request));

        return $this->respond(['stored_events' => $count]);
    }

    public function staticFixed(Request $request): JsonResponse
    {
        // Дані живуть лише в межах запиту; якщо їх треба накопичувати, їх пишуть у Redis/БД.
        $events = [$this->event($request)];

        return $this->respond(['stored_events' => count($events)]);
    }

    // ---- 2. Синглтон з Request ----

    public function singletonLeak(): JsonResponse
    {
        return $this->respond(['name' => app('leak.context.singleton')->name()]);
    }

    public function singletonFixed(): JsonResponse
    {
        // scoped(): новий екземпляр на кожен запит (Octane скидає scoped-прив'язки).
        return $this->respond(['name' => app('leak.context.scoped')->name()]);
    }

    // ---- 3. Необмежена мемоізація в синглтоні ----

    public function memoLeak(Request $request, ReportMemo $memo): JsonResponse
    {
        $memo->get($request->query('key', uniqid('', true)));

        return $this->respond(['memo_size' => $memo->size()]);
    }

    public function memoFixed(Request $request, BoundedReportMemo $memo): JsonResponse
    {
        $memo->get($request->query('key', uniqid('', true)));

        return $this->respond(['memo_size' => $memo->size()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function event(Request $request): array
    {
        return [
            'time' => microtime(true),
            'ip' => $request->ip(),
            'payload' => str_repeat('x', 10 * 1024), // ~10 КБ на запит
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function respond(array $data): JsonResponse
    {
        return response()->json($data + [
            'pid' => getmypid(),
            'memory_mb' => round(memory_get_usage() / 1048576, 2),
        ]);
    }
}
