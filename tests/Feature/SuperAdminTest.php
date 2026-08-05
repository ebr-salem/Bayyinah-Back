<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_sub_admin_cannot_manage_sub_admins(): void
    {
        $subAdmin = User::factory()->subAdmin()->create();
        $this->actingAs($subAdmin);

        $this->getJson('/api/v1/sub-admins')->assertForbidden();
        $this->postJson('/api/v1/sub-admins', [
            'name' => 'علي',
            'email' => 'ali@example.com',
            'password' => 'password',
        ])->assertForbidden();
    }

    public function test_super_admin_can_create_sub_admin(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($superAdmin);

        $response = $this->postJson('/api/v1/sub-admins', [
            'name' => 'علي',
            'email' => 'ali@example.com',
            'password' => 'secret123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'ali@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'ali@example.com',
            'role' => 'sub_admin',
        ]);
    }

    public function test_super_admin_can_update_sub_admin(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($superAdmin);

        $subAdmin = User::factory()->subAdmin()->create();

        $this->putJson("/api/v1/sub-admins/{$subAdmin->id}", [
            'name' => 'محمد المحدّث',
            'is_active' => false,
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $subAdmin->id,
            'name' => 'محمد المحدّث',
            'is_active' => false,
        ]);
    }

    public function test_super_admin_can_delete_sub_admin(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($superAdmin);

        $subAdmin = User::factory()->subAdmin()->create();

        $this->deleteJson("/api/v1/sub-admins/{$subAdmin->id}")->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $subAdmin->id]);
    }

    public function test_only_super_admin_can_update_settings(): void
    {
        $subAdmin = User::factory()->subAdmin()->create();
        $this->actingAs($subAdmin);

        $this->putJson('/api/v1/settings', [
            'settings' => ['ai_operations_monthly_limit' => '50'],
        ])->assertForbidden();
    }

    public function test_super_admin_can_update_settings(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($superAdmin);

        $this->putJson('/api/v1/settings', [
            'settings' => ['ai_operations_monthly_limit' => '50'],
        ])->assertOk()
            ->assertJsonPath('data.ai_operations_monthly_limit', '50');

        $this->assertDatabaseHas('global_settings', [
            'setting_key' => 'ai_operations_monthly_limit',
            'setting_value' => '50',
        ]);
    }
}
