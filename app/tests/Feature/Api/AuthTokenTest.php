<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_obtain_token_and_access_me(): void
    {
        $user = User::factory()->create(['email' => 'u@diploma.test']);

        $token = $this->postJson('/api/auth/token', ['email' => 'u@diploma.test', 'password' => 'password'])
            ->assertCreated()
            ->json('token');

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJson(['id' => $user->id, 'role' => 'manager']);
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'u@diploma.test']);

        $this->postJson('/api/auth/token', ['email' => 'u@diploma.test', 'password' => 'wrong'])
            ->assertUnprocessable();
    }

    public function test_protected_routes_require_token(): void
    {
        $this->getJson('/api/orders')->assertUnauthorized();
        $this->getJson('/api/me')->assertUnauthorized();
    }
}
