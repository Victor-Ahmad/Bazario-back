<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\ServiceProvider;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_provider_can_confirm_and_complete(): void
    {
        $customer = User::factory()->create();
        $providerUser = User::factory()->create();
        $provider = ServiceProvider::factory()->create(['user_id' => $providerUser->id]);

        $service = Service::factory()->create([
            'provider_id' => $provider->id,
            'is_active' => true,
        ]);

        $start = Carbon::now('UTC')->addDays(2)->setTime(10, 0);
        $booking = ServiceBooking::create([
            'service_id' => $service->id,
            'provider_user_id' => $providerUser->id,
            'customer_user_id' => $customer->id,
            'status' => 'requested',
            'starts_at' => $start,
            'ends_at' => (clone $start)->addHour(),
            'timezone' => 'UTC',
        ]);

        Sanctum::actingAs($customer);
        $this->patchJson("/api/bookings/{$booking->id}/confirm", [], [
            'Accept-Language' => 'en',
        ])->assertStatus(403);

        Sanctum::actingAs($providerUser);
        $this->patchJson("/api/bookings/{$booking->id}/confirm", [], [
            'Accept-Language' => 'en',
        ])->assertStatus(200);

        $this->patchJson("/api/bookings/{$booking->id}/complete", [], [
            'Accept-Language' => 'en',
        ])->assertStatus(200);
    }
}
