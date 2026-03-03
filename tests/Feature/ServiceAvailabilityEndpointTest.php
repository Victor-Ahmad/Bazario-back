<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\ServiceProvider;
use App\Models\ServiceProviderWorkingHour;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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

    public function test_availability_excludes_fully_booked_slot(): void
    {
        $providerUser = User::factory()->create();
        $customerUser = User::factory()->create();
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

        $date = Carbon::now('UTC')->addDays(1)->startOfDay();
        ServiceProviderWorkingHour::create([
            'service_provider_id' => $provider->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $slotStart = $date->copy()->setTime(10, 0);
        ServiceBooking::create([
            'service_id' => $service->id,
            'provider_user_id' => $providerUser->id,
            'customer_user_id' => $customerUser->id,
            'status' => 'confirmed',
            'starts_at' => $slotStart->copy()->utc(),
            'ends_at' => $slotStart->copy()->addHour()->utc(),
            'timezone' => 'UTC',
        ]);

        $response = $this->getJson('/api/services/' . $service->id . '/availability?date=' . $date->format('Y-m-d') . '&timezone=UTC')
            ->assertStatus(200);

        $slots = $response->json('slots');
        $this->assertNotEmpty($slots);

        $blocked = collect($slots)->first(function ($slot) use ($slotStart) {
            return Carbon::parse($slot['starts_at'])->equalTo($slotStart);
        });

        $this->assertNull($blocked, 'Fully booked slot should be excluded.');
    }

    public function test_availability_returns_remaining_capacity_for_partially_booked_slot(): void
    {
        $providerUser = User::factory()->create();
        $customerUser = User::factory()->create();
        $provider = ServiceProvider::factory()->create([
            'user_id' => $providerUser->id,
            'timezone' => 'UTC',
        ]);

        $service = Service::factory()->create([
            'provider_id' => $provider->id,
            'duration_minutes' => 60,
            'slot_interval_minutes' => 60,
            'max_concurrent_bookings' => 2,
        ]);

        $date = Carbon::now('UTC')->addDays(1)->startOfDay();
        ServiceProviderWorkingHour::create([
            'service_provider_id' => $provider->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $slotStart = $date->copy()->setTime(9, 0);
        ServiceBooking::create([
            'service_id' => $service->id,
            'provider_user_id' => $providerUser->id,
            'customer_user_id' => $customerUser->id,
            'status' => 'confirmed',
            'starts_at' => $slotStart->copy()->utc(),
            'ends_at' => $slotStart->copy()->addHour()->utc(),
            'timezone' => 'UTC',
        ]);

        $response = $this->getJson('/api/services/' . $service->id . '/availability?date=' . $date->format('Y-m-d') . '&timezone=UTC')
            ->assertStatus(200);

        $slot = collect($response->json('slots'))->first(function ($item) use ($slotStart) {
            return Carbon::parse($item['starts_at'])->equalTo($slotStart);
        });

        $this->assertNotNull($slot, 'Partially booked slot should still be available.');
        $this->assertSame(1, $slot['remaining_capacity']);
    }

    public function test_availability_can_ignore_current_booking_for_reschedule(): void
    {
        $providerUser = User::factory()->create();
        $customerUser = User::factory()->create();
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

        $date = Carbon::now('UTC')->addDays(2)->startOfDay();
        ServiceProviderWorkingHour::create([
            'service_provider_id' => $provider->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $slotStart = $date->copy()->setTime(10, 0);
        $booking = ServiceBooking::create([
            'service_id' => $service->id,
            'provider_user_id' => $providerUser->id,
            'customer_user_id' => $customerUser->id,
            'status' => 'confirmed',
            'starts_at' => $slotStart->copy()->utc(),
            'ends_at' => $slotStart->copy()->addHour()->utc(),
            'timezone' => 'UTC',
        ]);

        Sanctum::actingAs($customerUser);

        $response = $this->getJson(
            '/api/services/' . $service->id . '/availability?date='
            . $date->format('Y-m-d')
            . '&timezone=UTC&ignore_booking_id='
            . $booking->id
        )->assertStatus(200);

        $slot = collect($response->json('slots'))->first(function ($item) use ($slotStart) {
            return Carbon::parse($item['starts_at'])->equalTo($slotStart);
        });

        $this->assertNotNull($slot, 'Current booking slot should stay selectable while rescheduling.');
    }

    public function test_availability_rejects_ignoring_booking_for_unrelated_user(): void
    {
        $providerUser = User::factory()->create();
        $customerUser = User::factory()->create();
        $otherUser = User::factory()->create();
        $provider = ServiceProvider::factory()->create([
            'user_id' => $providerUser->id,
            'timezone' => 'UTC',
        ]);

        $service = Service::factory()->create([
            'provider_id' => $provider->id,
        ]);

        $date = Carbon::now('UTC')->addDays(2)->startOfDay();
        ServiceProviderWorkingHour::create([
            'service_provider_id' => $provider->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $booking = ServiceBooking::create([
            'service_id' => $service->id,
            'provider_user_id' => $providerUser->id,
            'customer_user_id' => $customerUser->id,
            'status' => 'confirmed',
            'starts_at' => $date->copy()->setTime(10, 0)->utc(),
            'ends_at' => $date->copy()->setTime(11, 0)->utc(),
            'timezone' => 'UTC',
        ]);

        Sanctum::actingAs($otherUser);

        $this->getJson(
            '/api/services/' . $service->id . '/availability?date='
            . $date->format('Y-m-d')
            . '&timezone=UTC&ignore_booking_id='
            . $booking->id
        )->assertStatus(403);
    }
}
