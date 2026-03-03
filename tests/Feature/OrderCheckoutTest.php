<?php

namespace Tests\Feature;

use App\Models\OrderItem;
use App\Models\Category;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\ServiceProvider;
use App\Models\StripePayment;
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

    private function fakeStripeCheckoutClient(?array &$capture = null)
    {
        return new class($capture) extends \Stripe\StripeClient {
            public $checkout;
            private $capture;

            public function __construct(?array &$capture = null)
            {
                $this->capture = &$capture;
                $capture = &$this->capture;

                $this->checkout = new class($capture) {
                    public $sessions;
                    public function __construct(?array &$capture = null)
                    {
                        $this->sessions = new class($capture) {
                            private $capture;

                            public function __construct(?array &$capture = null)
                            {
                                $this->capture = &$capture;
                            }

                            public function create(array $params, array $opts = [])
                            {
                                if (is_array($this->capture)) {
                                    $this->capture['params'] = $params;
                                    $this->capture['opts'] = $opts;
                                }

                                return (object) [
                                    'id' => 'cs_test_123',
                                    'url' => 'https://checkout.stripe.com/pay/cs_test_123',
                                ];
                            }

                            public function retrieve(string $id, array $params = [])
                            {
                                return (object) [
                                    'id' => $id,
                                    'payment_status' => 'paid',
                                    'status' => 'complete',
                                    'payment_intent' => 'pi_session_paid_1',
                                    'amount_total' => 1000,
                                    'currency' => 'eur',
                                    'metadata' => [
                                        'order_id' => '1',
                                    ],
                                ];
                            }
                        };
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

    public function test_checkout_session_returns_redirect_url(): void
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

        OrderItem::create([
            'order_id' => $order->id,
            'purchasable_type' => \App\Models\Product::class,
            'purchasable_id' => 1,
            'title_snapshot' => 'Demo Headphones',
            'description_snapshot' => 'Wireless headphones with noise cancelling.',
            'quantity' => 1,
            'unit_amount' => 1000,
            'gross_amount' => 1000,
            'platform_fee_amount' => 100,
            'net_amount' => 900,
            'payee_user_id' => $buyer->id,
            'status' => 'pending',
        ]);

        $capture = [];
        $this->app->instance(\Stripe\StripeClient::class, $this->fakeStripeCheckoutClient($capture));

        Sanctum::actingAs($buyer);

        $response = $this->postJson("/api/orders/{$order->id}/checkout-session", [], [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['checkout_url', 'checkout_session_id']);

        $lineItem = $capture['params']['line_items'][0] ?? null;
        $this->assertStringContainsString('session_id={CHECKOUT_SESSION_ID}', $capture['params']['success_url'] ?? '');

        $this->assertSame('Product: Demo Headphones', $lineItem['price_data']['product_data']['name'] ?? null);
        $this->assertStringContainsString(
            'Wireless headphones with noise cancelling.',
            $lineItem['price_data']['product_data']['description'] ?? '',
        );
        $this->assertStringContainsString(
            'Unit price: 10.00 EUR',
            $lineItem['price_data']['product_data']['description'] ?? '',
        );
    }

    public function test_checkout_session_includes_service_booking_details(): void
    {
        $buyer = User::factory()->create();
        $providerUser = User::factory()->create();
        $category = Category::create([
            'name' => 'Services',
            'description' => 'Service category',
        ]);
        $provider = ServiceProvider::create([
            'user_id' => $providerUser->id,
            'name' => 'Studio Provider',
            'address' => 'Berlin',
            'description' => 'Photography studio',
            'status' => 'approved',
        ]);
        $service = Service::create([
            'provider_id' => $provider->id,
            'category_id' => $category->id,
            'title' => ['en' => 'Portrait Photography Session'],
            'slug' => 'portrait-photography-session',
            'description' => ['en' => 'Professional outdoor portrait session.'],
            'price' => 55,
            'currency_iso' => 'EUR',
            'duration_minutes' => 90,
            'location_type' => 'online',
            'is_active' => true,
        ]);

        $order = Order::create([
            'buyer_id' => $buyer->id,
            'status' => 'draft',
            'currency_iso' => 'EUR',
            'subtotal_amount' => 5500,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 5500,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'purchasable_type' => \App\Models\Service::class,
            'purchasable_id' => $service->id,
            'title_snapshot' => 'Portrait Photography Session',
            'description_snapshot' => 'Professional outdoor portrait session.',
            'quantity' => 1,
            'unit_amount' => 5500,
            'gross_amount' => 5500,
            'platform_fee_amount' => 500,
            'net_amount' => 5000,
            'payee_user_id' => $providerUser->id,
            'status' => 'pending',
        ]);

        ServiceBooking::create([
            'order_item_id' => $item->id,
            'service_id' => $service->id,
            'provider_user_id' => $providerUser->id,
            'customer_user_id' => $buyer->id,
            'status' => 'requested',
            'starts_at' => '2026-05-10 08:00:00',
            'ends_at' => '2026-05-10 09:30:00',
            'timezone' => 'Europe/Berlin',
            'location_type' => 'online',
            'location_payload' => null,
        ]);

        $capture = [];
        $this->app->instance(\Stripe\StripeClient::class, $this->fakeStripeCheckoutClient($capture));

        Sanctum::actingAs($buyer);

        $this->postJson("/api/orders/{$order->id}/checkout-session", [], [
            'Accept-Language' => 'en',
        ])->assertStatus(200);

        $lineItem = $capture['params']['line_items'][0] ?? null;

        $this->assertSame(
            'Service booking: Portrait Photography Session',
            $lineItem['price_data']['product_data']['name'] ?? null,
        );
        $this->assertStringContainsString(
            'Booking: 2026-05-10 10:00 to 11:30 (Europe/Berlin)',
            $lineItem['price_data']['product_data']['description'] ?? '',
        );
        $this->assertSame(
            'Service',
            $lineItem['price_data']['product_data']['metadata']['purchasable_type'] ?? null,
        );
    }

    public function test_checkout_session_reconcile_marks_order_paid(): void
    {
        $buyer = User::factory()->create();
        $order = Order::create([
            'buyer_id' => $buyer->id,
            'status' => 'requires_payment',
            'currency_iso' => 'EUR',
            'subtotal_amount' => 1000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 1000,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'purchasable_type' => \App\Models\Product::class,
            'purchasable_id' => 1,
            'title_snapshot' => 'Demo item',
            'description_snapshot' => 'Demo description',
            'quantity' => 1,
            'unit_amount' => 1000,
            'gross_amount' => 1000,
            'platform_fee_amount' => 100,
            'net_amount' => 900,
            'payee_user_id' => $buyer->id,
            'status' => 'pending',
        ]);

        $capture = [];
        $this->app->instance(\Stripe\StripeClient::class, $this->fakeStripeCheckoutClient($capture));

        Sanctum::actingAs($buyer);

        $response = $this->postJson("/api/orders/{$order->id}/checkout-session/reconcile", [
            'session_id' => 'cs_paid_123',
        ], [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $order->id,
                'status' => 'paid',
            ]);

        $this->assertDatabaseHas('stripe_payments', [
            'order_id' => $order->id,
            'payment_intent_id' => 'pi_session_paid_1',
            'status' => 'succeeded',
        ]);

        $this->assertDatabaseHas('wallet_ledger_entries', [
            'order_id' => $order->id,
            'type' => 'sale_pending',
            'amount' => 900,
        ]);
    }
}
