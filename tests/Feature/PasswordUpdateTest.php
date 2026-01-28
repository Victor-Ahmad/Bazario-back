<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_password_requires_correct_old_password(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('oldpass'),
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/update-password', [
            'old_password' => 'wrong',
            'password' => 'newpass123',
        ], ['Accept-Language' => 'en'])->assertStatus(400);
    }

    public function test_update_password_success(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('oldpass'),
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/update-password', [
            'old_password' => 'oldpass',
            'password' => 'newpass123',
        ], ['Accept-Language' => 'en'])->assertStatus(200);
    }
}
