<?php

namespace Tests\Feature;

use App\Models\ServiceProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceAvailabilityValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_overlapping_intervals_are_rejected(): void
    {
        Role::create(['name' => 'service_provider']);
        $user = User::factory()->create();
        $user->assignRole('service_provider');
        ServiceProvider::factory()->create(['user_id' => $user->id, 'timezone' => 'UTC']);

        Sanctum::actingAs($user);

        $this->putJson('/api/service_provider/working-hours', [
            'timezone' => 'UTC',
            'days' => [
                [
                    'day_of_week' => 1,
                    'intervals' => [
                        ['start_time' => '09:00', 'end_time' => '12:00'],
                        ['start_time' => '11:00', 'end_time' => '14:00'],
                    ],
                ],
            ],
        ], ['Accept-Language' => 'en'])->assertStatus(422);
    }
}
