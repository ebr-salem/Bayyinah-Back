<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_login_and_receive_a_token(): void
    {
        $user = User::factory()->superAdmin()->create(['password' => 'secret123']);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success', 'message',
                'data' => ['token', 'user' => ['id', 'name', 'email', 'role']],
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_with_invalid_credentials_returns_401(): void
    {
        User::factory()->superAdmin()->create(['password' => 'secret123']);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'wrong@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(401);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->superAdmin()->inactive()->create(['password' => 'secret123']);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(403);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->superAdmin()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/logout');

        $response->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
