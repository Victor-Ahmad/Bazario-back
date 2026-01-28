<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUpgradeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_upgrade_requests(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/upgrade-requests', [
            'Accept-Language' => 'en',
        ])->assertStatus(403);
    }

    public function test_admin_can_access_upgrade_requests(): void
    {
        $adminRole = Role::create(['name' => 'admin']);
        $user = User::factory()->create();
        $user->assignRole($adminRole);
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/upgrade-requests', [
            'Accept-Language' => 'en',
        ])->assertStatus(200);
    }
}
