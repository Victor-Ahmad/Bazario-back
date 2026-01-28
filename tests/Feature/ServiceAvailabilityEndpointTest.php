<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\ServiceProviderWorkingHour;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceAvailabilityEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_availability_returns_slots_for_future_day(): void
    {
        $providerUser = User::factory()->create();
        $provider = ServiceProvider::factory()->create([
            'user_id' => $providerUser->id,
            'timezone' => 'UTC',
        ]);

        $service = Service::factory()->create([
            'provider_id' => $provider->id,
            'duration_minutes' => 60,
            'slot_interval_minutes' => 60,
            'max_concurrent_bookings' => 1,
        ]);

        $date = Carbon::now('UTC')->addDays(1);
        ServiceProviderWorkingHour::create([
            'service_provider_id' => $provider->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $this->getJson('/api/services/' . $service->id . '/availability?date=' . $date->format('Y-m-d') . '&timezone=UTC')
            ->assertStatus(200)
            ->assertJsonStructure(['slots']);
    }

    public function test_availability_returns_empty_for_past_day(): void
    {
        $providerUser = User::factory()->create();
        $provider = ServiceProvider::factory()->create([
            'user_id' => $providerUser->id,
            'timezone' => 'UTC',
        ]);

        $service = Service::factory()->create([
            'provider_id' => $provider->id,
        ]);

        $date = Carbon::now('UTC')->subDays(2);
        ServiceProviderWorkingHour::create([
            'service_provider_id' => $provider->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $this->getJson('/api/services/' . $service->id . '/availability?date=' . $date->format('Y-m-d') . '&timezone=UTC')
            ->assertStatus(200)
            ->assertJson([
                'slots' => [],
            ]);
    }
}
