<?php

namespace Tests\Feature\Bench;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueBenchTokensTest extends TestCase
{
    use RefreshDatabase;

    public function test_tokens_are_issued_and_rotated(): void
    {
        $user = User::factory()->create(['email' => 'bench01@diploma.test']);
        $path = tempnam(sys_get_temp_dir(), 'tok');

        $this->artisan('bench:tokens', ['--output' => $path])->assertSuccessful();
        $this->artisan('bench:tokens', ['--output' => $path])->assertSuccessful();

        $tokens = json_decode(file_get_contents($path), true)['tokens'];
        $this->assertCount(1, $tokens);
        $this->assertSame(1, $user->tokens()->count());

        $this->withToken($tokens[0])->getJson('/api/me')->assertOk();
        unlink($path);
    }
}
