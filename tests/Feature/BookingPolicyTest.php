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

class BookingPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_cancel_after_cutoff(): void
    {
        $customer = User::factory()->create();
        $providerUser = User::factory()->create();
        $provider = ServiceProvider::factory()->create([
            'user_id' => $providerUser->id,
            'timezone' => 'UTC',
        ]);

        $start = Carbon::now('UTC')->addHours(2)->setTime(10, 0);
        ServiceProviderWorkingHour::create([
            'service_provider_id' => $provider->id,
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '18:00',
        ]);

        $service = Service::factory()->create([
            'provider_id' => $provider->id,
            'is_active' => true,
            'cancel_cutoff_hours' => 24,
        ]);

        $booking = ServiceBooking::create([
            'service_id' => $service->id,
            'provider_user_id' => $providerUser->id,
            'customer_user_id' => $customer->id,
            'status' => 'confirmed',
            'starts_at' => $start,
            'ends_at' => (clone $start)->addHour(),
            'timezone' => 'UTC',
        ]);

        Sanctum::actingAs($customer);

        $this->patchJson("/api/bookings/{$booking->id}/cancel", [], [
            'Accept-Language' => 'en',
        ])->assertStatus(422);
    }

    public function test_customer_can_reschedule_before_cutoff(): void
    {
        $customer = User::factory()->create();
        $providerUser = User::factory()->create();
        $provider = ServiceProvider::factory()->create([
            'user_id' => $providerUser->id,
            'timezone' => 'UTC',
        ]);

        $initial = Carbon::now('UTC')->addDays(3)->setTime(10, 0);
        $newStart = Carbon::now('UTC')->addDays(4)->setTime(11, 0);

        ServiceProviderWorkingHour::create([
            'service_provider_id' => $provider->id,
            'day_of_week' => $newStart->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '18:00',
        ]);

        $service = Service::factory()->create([
            'provider_id' => $provider->id,
            'is_active' => true,
            'edit_cutoff_hours' => 24,
        ]);

        $booking = ServiceBooking::create([
            'service_id' => $service->id,
            'provider_user_id' => $providerUser->id,
            'customer_user_id' => $customer->id,
            'status' => 'requested',
            'starts_at' => $initial,
            'ends_at' => (clone $initial)->addHour(),
            'timezone' => 'UTC',
        ]);

        Sanctum::actingAs($customer);

        $response = $this->patchJson(
            "/api/bookings/{$booking->id}/reschedule",
            [
                'starts_at' => $newStart->toDateTimeString(),
                'timezone' => 'UTC',
            ],
            ['Accept-Language' => 'en']
        );

        $response->assertStatus(200);
    }
}
