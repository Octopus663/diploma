<?php

namespace Tests\Feature;

use App\Models\Counterparty;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_is_deterministic(): void
    {
        putenv('SEED_SCALE=0.002');

        $this->seed(DatabaseSeeder::class);
        $first = $this->fingerprint();

        foreach (['documents', 'order_items', 'orders', 'counterparties', 'products', 'templates'] as $table) {
            DB::table($table)->delete();
        }

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($first, $this->fingerprint());
        $this->assertGreaterThan(0, Order::query()->count());
        $this->assertTrue(User::query()->where('email', 'bench01@diploma.test')->exists());

        putenv('SEED_SCALE');
    }

    private function fingerprint(): string
    {
        return md5(json_encode([
            Counterparty::query()->orderBy('id')->pluck('name'),
            Order::query()->orderBy('id')->get(['counterparty_id', 'total', 'ordered_at'])->toArray(),
            DB::table('order_items')->orderBy('id')->pluck('total'),
        ]));
    }
}
