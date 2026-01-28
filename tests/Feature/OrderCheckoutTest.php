<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function fakeStripeClient()
    {
        return new class extends \Stripe\StripeClient {
            public $paymentIntents;
            public function __construct()
            {
                $this->paymentIntents = new class {
                    public function create(array $params, array $opts = [])
                    {
                        return (object) [
                            'id' => 'pi_test_123',
                            'status' => 'requires_payment_method',
                            'client_secret' => 'secret_test',
                        ];
                    }
                };
            }
        };
    }

    public function test_checkout_rejects_zero_total(): void
    {
        $buyer = User::factory()->create();
        $order = Order::create([
            'buyer_id' => $buyer->id,
            'status' => 'draft',
            'currency_iso' => 'EUR',
            'subtotal_amount' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
        ]);

        Sanctum::actingAs($buyer);

        $this->postJson("/api/orders/{$order->id}/checkout", [], [
            'Accept-Language' => 'en',
        ])->assertStatus(422);
    }

    public function test_checkout_creates_payment_intent_with_stub(): void
    {
        $buyer = User::factory()->create();
        $order = Order::create([
            'buyer_id' => $buyer->id,
            'status' => 'draft',
            'currency_iso' => 'EUR',
            'subtotal_amount' => 1000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 1000,
        ]);

        $this->app->instance(\Stripe\StripeClient::class, $this->fakeStripeClient());

        Sanctum::actingAs($buyer);

        $response = $this->postJson("/api/orders/{$order->id}/checkout", [], [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('client_secret'));
        $this->assertNotEmpty($response->json('payment_intent_id'));
    }
}
