<?php

namespace Database\Seeders;

use App\Models\User;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Детермінований сидер предметної області.
 *
 * Обсяг керується змінною SEED_SCALE (типово 1.0):
 *   1.0 -> 5 000 контрагентів, 2 000 товарів, 200 шаблонів,
 *          100 000 замовлень, ~500 000 позицій, 30 000 документів.
 * Фіксований seed (SEED_RANDOM, типово 42) дає однакові дані при кожному запуску,
 * тому результати вимірювань у різних режимах можна порівнювати.
 *
 * Для швидкості дані вставляються пакетами через Query Builder з явними id.
 */
class DomainSeeder extends Seeder
{
    private const CHUNK = 1000;

    private const BASE_DATE = '2026-01-01';

    private Generator $faker;

    public function run(): void
    {
        $scale = (float) env('SEED_SCALE', 1.0);
        $seed = (int) env('SEED_RANDOM', 42);

        $this->faker = FakerFactory::create('uk_UA');
        $this->faker->seed($seed);
        mt_srand($seed);

        $counts = [
            'templates' => max(10, (int) round(200 * min($scale, 1.0))),
            'products' => max(20, (int) round(2000 * $scale)),
            'counterparties' => max(20, (int) round(5000 * $scale)),
            'orders' => max(50, (int) round(100000 * $scale)),
            'documents' => max(20, (int) round(30000 * $scale)),
        ];

        $this->command?->info('SEED_SCALE='.$scale.' '.json_encode($counts));

        DB::disableQueryLog();

        $this->seedTemplates($counts['templates']);
        $prices = $this->seedProducts($counts['products']);
        $this->seedCounterparties($counts['counterparties']);
        $orderTotals = $this->seedOrders($counts['orders'], $counts['counterparties'], $prices);
        $this->seedDocuments($counts['documents'], $counts['templates'], $orderTotals);

        $this->resetSequences(['templates', 'products', 'counterparties', 'orders', 'order_items', 'documents']);
    }

    private function seedTemplates(int $count): void
    {
        $types = ['invoice' => 'Рахунок', 'act' => 'Акт виконаних робіт', 'contract' => 'Договір', 'report' => 'Звіт'];
        $fields = [
            'invoice' => ['number', 'date', 'counterparty', 'items', 'total', 'vat'],
            'act' => ['number', 'date', 'counterparty', 'items', 'total', 'signatures'],
            'contract' => ['number', 'date', 'counterparty', 'subject', 'terms', 'total'],
            'report' => ['period', 'counterparty', 'orders', 'totals', 'chart'],
        ];
        $now = now();
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $type = array_keys($types)[($i - 1) % 4];
            $rows[] = [
                'id' => $i,
                'code' => sprintf('TPL-%04d', $i),
                'name' => $types[$type].' №'.$i,
                'type' => $type,
                'description' => $this->faker->sentence(8),
                'fields' => json_encode($fields[$type]),
                'is_active' => $i % 10 !== 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insert('templates', $rows);
    }

    /**
     * @return array<int, float> ціни товарів за id
     */
    private function seedProducts(int $count): array
    {
        $categories = ['services', 'hardware', 'software', 'consulting', 'support'];
        $units = ['шт', 'год', 'посл', 'міс'];
        $now = now();
        $rows = [];
        $prices = [];

        for ($i = 1; $i <= $count; $i++) {
            $price = mt_rand(1000, 500000) / 100;
            $prices[$i] = $price;
            $rows[] = [
                'id' => $i,
                'sku' => sprintf('SKU-%05d', $i),
                'name' => mb_convert_case($this->faker->words(3, true), MB_CASE_TITLE),
                'category' => $categories[mt_rand(0, count($categories) - 1)],
                'unit' => $units[mt_rand(0, count($units) - 1)],
                'price' => $price,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) === self::CHUNK) {
                $this->insert('products', $rows);
                $rows = [];
            }
        }

        $this->insert('products', $rows);

        return $prices;
    }

    private function seedCounterparties(int $count): void
    {
        $now = now();
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $company = $i % 4 !== 0;
            $rows[] = [
                'id' => $i,
                'name' => $company ? $this->faker->company() : $this->faker->name(),
                'code' => $company ? sprintf('%08d', 10000000 + $i) : sprintf('%010d', 2000000000 + $i),
                'type' => $company ? 'company' : 'individual',
                'email' => 'contact'.$i.'@example.com',
                'phone' => sprintf('+380%09d', 500000000 + $i),
                'city' => $this->faker->city(),
                'address' => $this->faker->streetAddress(),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) === self::CHUNK) {
                $this->insert('counterparties', $rows);
                $rows = [];
            }
        }

        $this->insert('counterparties', $rows);
    }

    /**
     * @param  array<int, float>  $prices
     * @return array<int, array{0:int,1:float}> [counterparty_id, total] для частини замовлень (для документів)
     */
    private function seedOrders(int $count, int $counterparties, array $prices): array
    {
        $statuses = ['new', 'processing', 'completed', 'completed', 'completed', 'cancelled'];
        $productCount = count($prices);
        $userIds = User::query()->orderBy('id')->pluck('id')->all();
        $base = strtotime(self::BASE_DATE);
        $now = now();
        $orders = [];
        $items = [];
        $itemId = 0;
        $forDocuments = [];

        for ($i = 1; $i <= $count; $i++) {
            $counterpartyId = mt_rand(1, $counterparties);
            $lines = mt_rand(1, 9);
            $total = 0.0;

            for ($l = 0; $l < $lines; $l++) {
                $productId = mt_rand(1, $productCount);
                $quantity = mt_rand(1, 20);
                $lineTotal = round($quantity * $prices[$productId], 2);
                $total += $lineTotal;
                $items[] = [
                    'id' => ++$itemId,
                    'order_id' => $i,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'price' => $prices[$productId],
                    'total' => $lineTotal,
                ];
            }

            $orders[] = [
                'id' => $i,
                'number' => sprintf('ORD-%08d', $i),
                'counterparty_id' => $counterpartyId,
                'user_id' => $userIds[mt_rand(0, count($userIds) - 1)],
                'status' => $statuses[mt_rand(0, count($statuses) - 1)],
                'ordered_at' => date('Y-m-d', $base - mt_rand(0, 730) * 86400),
                'total' => round($total, 2),
                'currency' => 'UAH',
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($i % 3 === 0) {
                $forDocuments[$i] = [$counterpartyId, round($total, 2)];
            }

            if (count($orders) === self::CHUNK) {
                $this->insert('orders', $orders);
                $this->insert('order_items', $items);
                $orders = [];
                $items = [];
            }
        }

        $this->insert('orders', $orders);
        $this->insert('order_items', $items);
        $this->command?->info("orders: {$count}, order_items: {$itemId}");

        return $forDocuments;
    }

    /**
     * @param  array<int, array{0:int,1:float}>  $orderTotals
     */
    private function seedDocuments(int $count, int $templates, array $orderTotals): void
    {
        $statuses = ['draft', 'issued', 'issued', 'signed', 'cancelled'];
        $orderIds = array_keys($orderTotals);
        $base = strtotime(self::BASE_DATE);
        $now = now();
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $orderId = $orderIds[($i - 1) % count($orderIds)];
            [$counterpartyId, $total] = $orderTotals[$orderId];
            $templateId = mt_rand(1, $templates);
            $status = $statuses[mt_rand(0, count($statuses) - 1)];
            $rows[] = [
                'id' => $i,
                'number' => sprintf('DOC-%08d', $i),
                'template_id' => $templateId,
                'counterparty_id' => $counterpartyId,
                'order_id' => $orderId,
                'user_id' => null,
                'title' => 'Документ до замовлення '.sprintf('ORD-%08d', $orderId),
                'status' => $status,
                'payload' => json_encode([
                    'order' => sprintf('ORD-%08d', $orderId),
                    'total' => $total,
                    'vat' => round($total / 6, 2),
                    'comment' => $this->faker->sentence(6),
                ], JSON_UNESCAPED_UNICODE),
                'amount' => $total,
                'issued_at' => $status === 'draft' ? null : date('Y-m-d H:i:s', $base - mt_rand(0, 365) * 86400),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) === self::CHUNK) {
                $this->insert('documents', $rows);
                $rows = [];
            }
        }

        $this->insert('documents', $rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function insert(string $table, array $rows): void
    {
        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    /**
     * Після вставки з явними id синхронізувати послідовності PostgreSQL.
     *
     * @param  array<int, string>  $tables
     */
    private function resetSequences(array $tables): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($tables as $table) {
            DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 1))");
        }
    }
}
