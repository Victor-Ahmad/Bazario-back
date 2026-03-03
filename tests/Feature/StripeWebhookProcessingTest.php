<?php

namespace Tests\Feature;

use App\Models\ConnectAccount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'stripe.webhook_secret' => 'whsec_test_local',
            'queue.default' => 'sync',
        ]);
    }

    public function test_payment_intent_succeeded_webhook_marks_order_paid_and_creates_ledger(): void
    {
        [$order, $item] = $this->makePendingOrder();

        $event = [
            'id' => 'evt_payment_success_1',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_success_1',
                    'status' => 'succeeded',
                    'latest_charge' => 'ch_success_1',
                    'amount' => 5000,
                    'currency' => 'eur',
                    'metadata' => [
                        'order_id' => (string) $order->id,
                    ],
                ],
            ],
        ];

        $this->postSignedStripeWebhook($event)->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('stripe_payments', [
            'order_id' => $order->id,
            'payment_intent_id' => 'pi_success_1',
            'status' => 'succeeded',
            'charge_id' => 'ch_success_1',
            'amount' => 5000,
            'currency_iso' => 'EUR',
        ]);

        $this->assertDatabaseHas('wallet_ledger_entries', [
            'order_item_id' => $item->id,
            'type' => 'sale_pending',
            'amount' => 4500,
            'currency_iso' => 'EUR',
        ]);

        $this->assertNotNull(
            StripeWebhookEvent::where('event_id', 'evt_payment_success_1')->value('processed_at')
        );
    }

    public function test_duplicate_payment_success_webhook_is_idempotent(): void
    {
        [$order, $item] = $this->makePendingOrder();

        $event = [
            'id' => 'evt_payment_duplicate_1',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_duplicate_1',
                    'status' => 'succeeded',
                    'amount' => 5000,
                    'currency' => 'eur',
                    'metadata' => [
                        'order_id' => (string) $order->id,
                    ],
                ],
            ],
        ];

        $this->postSignedStripeWebhook($event)->assertOk();
        $this->postSignedStripeWebhook($event)->assertOk();

        $this->assertSame(1, StripeWebhookEvent::where('event_id', 'evt_payment_duplicate_1')->count());
        $this->assertSame(
            1,
            WalletLedgerEntry::where('order_item_id', $item->id)
                ->where('type', 'sale_pending')
                ->count()
        );
    }

    public function test_payment_intent_failed_webhook_keeps_order_requiring_payment(): void
    {
        [$order] = $this->makePendingOrder();

        $event = [
            'id' => 'evt_payment_failed_1',
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_failed_1',
                    'status' => 'requires_payment_method',
                    'amount' => 5000,
                    'currency' => 'eur',
                    'metadata' => [
                        'order_id' => (string) $order->id,
                    ],
                ],
            ],
        ];

        $this->postSignedStripeWebhook($event)->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'requires_payment',
        ]);

        $this->assertDatabaseHas('stripe_payments', [
            'order_id' => $order->id,
            'payment_intent_id' => 'pi_failed_1',
            'status' => 'requires_payment_method',
            'amount' => 5000,
            'currency_iso' => 'EUR',
        ]);

        $this->assertDatabaseMissing('wallet_ledger_entries', [
            'order_id' => $order->id,
            'type' => 'sale_pending',
        ]);
    }

    public function test_account_updated_webhook_syncs_connect_account_flags(): void
    {
        $user = User::factory()->create();

        ConnectAccount::create([
            'user_id' => $user->id,
            'stripe_account_id' => 'acct_sync_1',
            'type' => 'express',
            'charges_enabled' => false,
            'payouts_enabled' => false,
            'details_submitted' => false,
            'requirements' => null,
        ]);

        $event = [
            'id' => 'evt_account_updated_1',
            'type' => 'account.updated',
            'data' => [
                'object' => [
                    'id' => 'acct_sync_1',
                    'charges_enabled' => true,
                    'payouts_enabled' => true,
                    'details_submitted' => true,
                    'requirements' => [
                        'currently_due' => [],
                    ],
                ],
            ],
        ];

        $this->postSignedStripeWebhook($event)->assertOk();

        $this->assertDatabaseHas('connect_accounts', [
            'user_id' => $user->id,
            'stripe_account_id' => 'acct_sync_1',
            'charges_enabled' => 1,
            'payouts_enabled' => 1,
            'details_submitted' => 1,
        ]);

        $this->assertNotNull(
            ConnectAccount::where('stripe_account_id', 'acct_sync_1')->value('onboarding_completed_at')
        );
    }

    public function test_invalid_webhook_signature_is_rejected(): void
    {
        [$order] = $this->makePendingOrder();

        $payload = json_encode([
            'id' => 'evt_bad_sig_1',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_bad_sig_1',
                    'status' => 'succeeded',
                    'amount' => 5000,
                    'currency' => 'eur',
                    'metadata' => [
                        'order_id' => (string) $order->id,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = $this->call(
            'POST',
            '/api/stripe/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't=' . time() . ',v1=invalid',
            ],
            $payload
        );

        $response->assertStatus(400);

        $this->assertDatabaseMissing('stripe_webhook_events', [
            'event_id' => 'evt_bad_sig_1',
        ]);
    }

    private function postSignedStripeWebhook(array $event)
    {
        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac(
            'sha256',
            $timestamp . '.' . $payload,
            (string) config('stripe.webhook_secret')
        );

        return $this->call(
            'POST',
            '/api/stripe/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            ],
            $payload
        );
    }

    private function makePendingOrder(): array
    {
        $buyer = User::factory()->create();
        $payee = User::factory()->create();

        $order = Order::create([
            'buyer_id' => $buyer->id,
            'status' => 'requires_payment',
            'currency_iso' => 'EUR',
            'subtotal_amount' => 5000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 5000,
            'transfer_group' => 'order_' . uniqid(),
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'purchasable_type' => 'App\\Models\\Product',
            'purchasable_id' => 1,
            'title_snapshot' => 'Stripe Test Item',
            'quantity' => 1,
            'unit_amount' => 5000,
            'gross_amount' => 5000,
            'platform_fee_amount' => 500,
            'net_amount' => 4500,
            'payee_user_id' => $payee->id,
            'status' => 'pending',
        ]);

        return [$order, $item];
    }
}
