<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Читання зведених результатів вимірювань для сторінки «Бенчмарки».
 *
 * Файл resources/benchmarks/summary.csv створює analysis/parse.py (етап 7).
 * Колонки: mode,scenario,vus,rps,p50_ms,p95_ms,p99_ms,error_rate,cpu_pct,mem_mb,runs
 */
class BenchmarkResults
{
    public const MODES = ['fpm', 'roadrunner', 'swoole', 'frankenphp'];

    public const MODE_LABELS = [
        'fpm' => 'PHP-FPM',
        'roadrunner' => 'RoadRunner',
        'swoole' => 'Swoole',
        'frankenphp' => 'FrankenPHP',
    ];

    public const MODE_COLORS = [
        'fpm' => '#6b7280',
        'roadrunner' => '#2563eb',
        'swoole' => '#16a34a',
        'frankenphp' => '#9333ea',
    ];

    public function __construct(private readonly ?string $path = null) {}

    public function path(): string
    {
        return $this->path ?? resource_path('benchmarks/summary.csv');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(): Collection
    {
        $path = $this->path();

        if (! is_file($path)) {
            return collect();
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, escape: '');
        $rows = [];

        while (($line = fgetcsv($handle, escape: '')) !== false) {
            if (count($line) !== count($header)) {
                continue;
            }

            $row = array_combine($header, $line);

            foreach ($row as $key => $value) {
                if (! in_array($key, ['mode', 'scenario'], true)) {
                    $row[$key] = (float) $value;
                }
            }

            $rows[] = $row;
        }

        fclose($handle);

        return collect($rows);
    }

    /**
     * @return array<int, string>
     */
    public function scenarios(): array
    {
        return $this->rows()->pluck('scenario')->unique()->values()->all();
    }

    /**
     * Конфігурація Chart.js: метрика від кількості віртуальних користувачів для одного сценарію.
     *
     * @return array<string, mixed>
     */
    public function lineChart(string $scenario, string $metric, string $title): array
    {
        $rows = $this->rows()->where('scenario', $scenario);
        $vus = $rows->pluck('vus')->unique()->sort()->values();

        $datasets = [];

        foreach (self::MODES as $mode) {
            $byVus = $rows->where('mode', $mode)->keyBy(fn ($r) => (string) $r['vus']);

            if ($byVus->isEmpty()) {
                continue;
            }

            $datasets[] = [
                'label' => self::MODE_LABELS[$mode],
                'data' => $vus->map(fn ($v) => isset($byVus[(string) $v]) ? round($byVus[(string) $v][$metric], 2) : null)->all(),
                'borderColor' => self::MODE_COLORS[$mode],
                'backgroundColor' => self::MODE_COLORS[$mode],
                'tension' => 0.2,
            ];
        }

        return [
            'type' => 'line',
            'data' => ['labels' => $vus->map(fn ($v) => (int) $v)->all(), 'datasets' => $datasets],
            'options' => [
                'plugins' => ['title' => ['display' => true, 'text' => $title]],
                'scales' => ['x' => ['title' => ['display' => true, 'text' => 'VU']]],
            ],
        ];
    }

    /**
     * Максимальний RPS кожного режиму за сценаріями (стовпчикова діаграма).
     *
     * @return array<string, mixed>
     */
    public function peakChart(): array
    {
        $rows = $this->rows();
        $scenarios = $this->scenarios();
        $datasets = [];

        foreach (self::MODES as $mode) {
            $modeRows = $rows->where('mode', $mode);

            if ($modeRows->isEmpty()) {
                continue;
            }

            $datasets[] = [
                'label' => self::MODE_LABELS[$mode],
                'data' => array_map(fn ($s) => round((float) $modeRows->where('scenario', $s)->max('rps'), 1), $scenarios),
                'backgroundColor' => self::MODE_COLORS[$mode],
            ];
        }

        return [
            'type' => 'bar',
            'data' => ['labels' => $scenarios, 'datasets' => $datasets],
            'options' => ['plugins' => ['title' => ['display' => true, 'text' => 'Пікова пропускна здатність, RPS']]],
        ];
    }

    /**
     * Таблиця: пік RPS і приріст відносно FPM.
     *
     * @return array<int, array<string, mixed>>
     */
    public function peakTable(): array
    {
        $rows = $this->rows();
        $table = [];

        foreach ($this->scenarios() as $scenario) {
            $fpm = (float) $rows->where('scenario', $scenario)->where('mode', 'fpm')->max('rps');
            $line = ['scenario' => $scenario];

            foreach (self::MODES as $mode) {
                $peak = (float) $rows->where('scenario', $scenario)->where('mode', $mode)->max('rps');
                $line[$mode] = $peak;
                $line[$mode.'_gain'] = $fpm > 0 && $mode !== 'fpm' ? round(($peak / $fpm - 1) * 100, 1) : null;
            }

            $table[] = $line;
        }

        return $table;
    }
}
