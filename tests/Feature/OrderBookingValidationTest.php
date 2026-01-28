<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\ServiceProvider;
use App\Models\ServiceProviderWorkingHour;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderBookingValidationTest extends TestCase
{
    use RefreshDatabase;

    private function makeDraftOrder(User $buyer): Order
    {
        return Order::create([
            'buyer_id' => $buyer->id,
            'status' => 'draft',
            'currency_iso' => 'EUR',
            'transfer_group' => null,
            'subtotal_amount' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
        ]);
    }

    public function test_order_booking_rejects_outside_working_hours(): void
    {
        $customer = User::factory()->create();
        $providerUser = User::factory()->create();
        $provider = ServiceProvider::factory()->create([
            'user_id' => $providerUser->id,
            'timezone' => 'UTC',
        ]);

        $start = Carbon::now('UTC')->addDays(1)->setTime(7, 0);

        ServiceProviderWorkingHour::create([
            'service_provider_id' => $provider->id,
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);

        $service = Service::factory()->create([
            'provider_id' => $provider->id,
            'is_active' => true,
            'duration_minutes' => 60,
        ]);

        $order = $this->makeDraftOrder($customer);

        Sanctum::actingAs($customer);

        $this->postJson(
            "/api/orders/{$order->id}/items",
            [
                'type' => 'service',
                'id' => $service->id,
                'starts_at' => $start->toDateTimeString(),
                'timezone' => 'UTC',
            ],
            ['Accept-Language' => 'en']
        )->assertStatus(422);
    }

    public function test_order_booking_sets_customer_user_id(): void
    {
        $customer = User::factory()->create();
        $providerUser = User::factory()->create();
        $provider = ServiceProvider::factory()->create([
            'user_id' => $providerUser->id,
            'timezone' => 'UTC',
        ]);

        $start = Carbon::now('UTC')->addDays(2)->setTime(10, 0);

        ServiceProviderWorkingHour::create([
            'service_provider_id' => $provider->id,
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '18:00',
        ]);

        $service = Service::factory()->create([
            'provider_id' => $provider->id,
            'is_active' => true,
            'duration_minutes' => 60,
        ]);

        $order = $this->makeDraftOrder($customer);

        Sanctum::actingAs($customer);

        $this->postJson(
            "/api/orders/{$order->id}/items",
            [
                'type' => 'service',
                'id' => $service->id,
                'starts_at' => $start->toDateTimeString(),
                'timezone' => 'UTC',
            ],
            ['Accept-Language' => 'en']
        )->assertStatus(200);

        $booking = ServiceBooking::where('service_id', $service->id)->first();
        $this->assertNotNull($booking);
        $this->assertSame($customer->id, $booking->customer_user_id);
    }
}
