<?php

namespace App\Services\Bench;

/**
 * Сценарій 4: CPU-навантаження. Генерація великого масиву рядків звіту,
 * агрегування, сортування та серіалізація в JSON перед формуванням звіту.
 * Дані детерміновані (генератор з фіксованим seed), тому результат однаковий у всіх режимах.
 */
class ReportCompute
{
    private const CATEGORIES = ['services', 'hardware', 'software', 'consulting', 'support'];

    private const REGIONS = ['Київ', 'Львів', 'Харків', 'Одеса', 'Дніпро', 'Запоріжжя', 'Вінниця', 'Полтава'];

    /**
     * @return array<string, mixed>
     */
    public function run(int $rows, int $seed, bool $includeRows): array
    {
        $data = $this->generate($rows, $seed);

        $byCategory = [];
        $byRegion = [];
        $totals = [];

        foreach ($data as $row) {
            $byCategory[$row['category']]['count'] = ($byCategory[$row['category']]['count'] ?? 0) + 1;
            $byCategory[$row['category']]['amount'] = ($byCategory[$row['category']]['amount'] ?? 0) + $row['amount'];
            $byRegion[$row['region']] = ($byRegion[$row['region']] ?? 0) + $row['amount'];
            $totals[] = $row['amount'];
        }

        ksort($byCategory);
        ksort($byRegion);
        sort($totals);

        usort($data, fn ($a, $b) => [$b['amount'], $a['id']] <=> [$a['amount'], $b['id']]);

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $summary = [
            'rows' => $rows,
            'seed' => $seed,
            'total' => round(array_sum($totals), 2),
            'avg' => round(array_sum($totals) / max(1, $rows), 2),
            'p50' => $this->percentile($totals, 50),
            'p95' => $this->percentile($totals, 95),
            'by_category' => array_map(fn ($c) => ['count' => $c['count'], 'amount' => round($c['amount'], 2)], $byCategory),
            'by_region' => array_map(fn ($v) => round($v, 2), $byRegion),
            'top' => array_slice($data, 0, 5),
            'serialized_bytes' => strlen($json),
            'checksum' => md5($json),
        ];

        if ($includeRows) {
            $summary['data'] = $data;
        }

        return $summary;
    }

    /**
     * Детермінований генератор (LCG), не залежить від глобального стану mt_rand.
     *
     * @return array<int, array<string, mixed>>
     */
    public function generate(int $rows, int $seed): array
    {
        $state = $seed & 0x7FFFFFFF;
        $next = function () use (&$state): int {
            $state = (1103515245 * $state + 12345) & 0x7FFFFFFF;

            return $state;
        };

        $data = [];

        for ($i = 1; $i <= $rows; $i++) {
            $quantity = $next() % 50 + 1;
            $price = ($next() % 500000) / 100;
            $data[] = [
                'id' => $i,
                'document' => sprintf('DOC-%08d', $next() % 100000),
                'category' => self::CATEGORIES[$next() % 5],
                'region' => self::REGIONS[$next() % 8],
                'quantity' => $quantity,
                'price' => $price,
                'amount' => round($quantity * $price, 2),
                'vat' => round($quantity * $price / 6, 2),
            ];
        }

        return $data;
    }

    /**
     * @param  array<int, float>  $sorted
     */
    private function percentile(array $sorted, int $p): float
    {
        if ($sorted === []) {
            return 0.0;
        }

        $index = (int) ceil($p / 100 * count($sorted)) - 1;

        return (float) $sorted[max(0, min($index, count($sorted) - 1))];
    }
}
