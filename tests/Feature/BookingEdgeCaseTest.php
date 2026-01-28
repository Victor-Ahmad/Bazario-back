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

class BookingEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_reschedule_rejects_past_start_time(): void
    {
        $customer = User::factory()->create();
        $providerUser = User::factory()->create();
        $provider = ServiceProvider::factory()->create([
            'user_id' => $providerUser->id,
            'timezone' => 'UTC',
        ]);

        $future = Carbon::now('UTC')->addDays(2)->setTime(10, 0);
        ServiceProviderWorkingHour::create([
            'service_provider_id' => $provider->id,
            'day_of_week' => $future->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '18:00',
        ]);

        $service = Service::factory()->create([
            'provider_id' => $provider->id,
            'is_active' => true,
        ]);

        $booking = ServiceBooking::create([
            'service_id' => $service->id,
            'provider_user_id' => $providerUser->id,
            'customer_user_id' => $customer->id,
            'status' => 'requested',
            'starts_at' => $future,
            'ends_at' => (clone $future)->addHour(),
            'timezone' => 'UTC',
        ]);

        Sanctum::actingAs($customer);

        $past = Carbon::now('UTC')->subDay()->setTime(10, 0);
        $this->patchJson("/api/bookings/{$booking->id}/reschedule", [
            'starts_at' => $past->toDateTimeString(),
            'timezone' => 'UTC',
        ], ['Accept-Language' => 'en'])->assertStatus(422);
    }
}
