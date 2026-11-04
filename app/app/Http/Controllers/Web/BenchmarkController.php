<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\BenchmarkResults;
use Illuminate\View\View;

class BenchmarkController extends Controller
{
    public function index(BenchmarkResults $results): View
    {
        $scenarios = $results->scenarios();

        return view('benchmarks.index', [
            'hasData' => $scenarios !== [],
            'scenarios' => $scenarios,
            'peakChart' => $results->peakChart(),
            'peakTable' => $results->peakTable(),
            'rpsCharts' => array_map(fn ($s) => $results->lineChart($s, 'rps', "RPS: {$s}"), $scenarios),
            'latencyCharts' => array_map(fn ($s) => $results->lineChart($s, 'p95_ms', "Затримка p95, мс: {$s}"), $scenarios),
            'memoryCharts' => array_map(fn ($s) => $results->lineChart($s, 'mem_mb', "Пам'ять контейнера, МБ: {$s}"), $scenarios),
            'labels' => BenchmarkResults::MODE_LABELS,
            'modes' => BenchmarkResults::MODES,
            'updatedAt' => is_file($results->path()) ? date('Y-m-d H:i', filemtime($results->path())) : null,
        ]);
    }
}
