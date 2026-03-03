<?php

namespace Tests\Feature;

use App\Models\ConnectAccount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPayoutFlowTest extends TestCase
{
    use RefreshDatabase;

    private function fakeStripeClient()
    {
        return new class extends \Stripe\StripeClient {
            public $transfers;

            public function __construct()
            {
                $this->transfers = new class {
                    public function create(array $params, array $options = [])
                    {
                        $suffix = substr(sha1($options['idempotency_key'] ?? 'default'), 0, 8);

                        return (object) [
                            'id' => 'tr_test_' . $suffix,
                            'status' => 'paid',
                        ];
                    }
                };
            }
        };
    }

    public function test_non_admin_cannot_access_admin_payouts_endpoint(): void
    {
        Role::create(['name' => 'customer']);

        $user = User::factory()->create();
        $user->assignRole('customer');

        Sanctum::actingAs($user);

        $this->getJson('/api/admin/payouts')->assertStatus(403);
    }

    public function test_admin_can_list_payout_balances(): void
    {
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'seller']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $seller = User::factory()->create();
        $seller->assignRole('seller');

        WalletLedgerEntry::create([
            'user_id' => $seller->id,
            'order_id' => null,
            'order_item_id' => null,
            'type' => 'sale_pending',
            'amount' => 2500,
            'currency_iso' => 'EUR',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/payouts');
        $response->assertStatus(200);

        $result = collect($response->json('result'));
        $row = $result->firstWhere('id', $seller->id);

        $this->assertNotNull($row);
        $this->assertEquals(2500, $row['balances'][0]['amount']);
    }

    public function test_admin_can_list_transfer_pending_balances_for_retry(): void
    {
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'seller']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $seller = User::factory()->create();
        $seller->assignRole('seller');

        $buyer = User::factory()->create();
        $order = Order::create([
            'buyer_id' => $buyer->id,
            'status' => 'paid',
            'currency_iso' => 'EUR',
            'subtotal_amount' => 3100,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 3100,
            'transfer_group' => 'order_pending_1',
        ]);

        WalletLedgerEntry::create([
            'user_id' => $seller->id,
            'order_id' => $order->id,
            'order_item_id' => null,
            'type' => 'transfer_pending',
            'amount' => 3100,
            'currency_iso' => 'EUR',
            'available_on' => now()->subMinute(),
            'metadata' => [
                'transfer_batch_key' => 'pending_batch',
            ],
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/payouts');
        $response->assertStatus(200);

        $result = collect($response->json('result'));
        $row = $result->firstWhere('id', $seller->id);

        $this->assertNotNull($row);
        $this->assertEquals(3100, $row['balances'][0]['amount']);
    }

    public function test_admin_pay_user_creates_transfer_and_marks_ledger_entries(): void
    {
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'seller']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $seller = User::factory()->create();
        $seller->assignRole('seller');

        ConnectAccount::create([
            'user_id' => $seller->id,
            'stripe_account_id' => 'acct_payee_1',
            'type' => 'express',
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'details_submitted' => true,
        ]);

        $buyer = User::factory()->create();
        $order = Order::create([
            'buyer_id' => $buyer->id,
            'status' => 'paid',
            'currency_iso' => 'EUR',
            'subtotal_amount' => 2000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 2000,
            'transfer_group' => 'order_seed_1',
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'purchasable_type' => 'App\\Models\\Product',
            'purchasable_id' => 1,
            'title_snapshot' => 'Payout Item',
            'quantity' => 1,
            'unit_amount' => 2000,
            'gross_amount' => 2000,
            'platform_fee_amount' => 200,
            'net_amount' => 1800,
            'payee_user_id' => $seller->id,
            'status' => 'pending',
        ]);

        WalletLedgerEntry::create([
            'user_id' => $seller->id,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'type' => 'sale_pending',
            'amount' => 1800,
            'currency_iso' => 'EUR',
            'available_on' => now()->subDay(),
            'metadata' => [],
        ]);

        $this->app->instance(\Stripe\StripeClient::class, $this->fakeStripeClient());

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/payouts/{$seller->id}/pay")
            ->assertStatus(200)
            ->assertJsonStructure(['message', 'transfers']);

        $this->assertDatabaseHas('stripe_transfers', [
            'payee_user_id' => $seller->id,
            'amount' => 1800,
        ]);

        $this->assertDatabaseHas('wallet_ledger_entries', [
            'user_id' => $seller->id,
            'order_item_id' => $item->id,
            'type' => 'transfer_out',
        ]);
    }

    public function test_admin_cannot_pay_customer_even_if_connected(): void
    {
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'customer']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $customer = User::factory()->create();
        $customer->assignRole('customer');

        ConnectAccount::create([
            'user_id' => $customer->id,
            'stripe_account_id' => 'acct_customer_1',
            'type' => 'express',
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'details_submitted' => true,
        ]);

        Sanctum::actingAs($admin);

        $this->app->instance(\Stripe\StripeClient::class, $this->fakeStripeClient());

        $this->postJson("/api/admin/payouts/{$customer->id}/pay")
            ->assertStatus(422);
    }

    public function test_admin_cannot_pay_user_without_connected_account(): void
    {
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'seller']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $seller = User::factory()->create();
        $seller->assignRole('seller');

        Sanctum::actingAs($admin);

        $this->app->instance(\Stripe\StripeClient::class, $this->fakeStripeClient());

        $this->postJson("/api/admin/payouts/{$seller->id}/pay")
            ->assertStatus(422);
    }

    public function test_admin_cannot_pay_when_connected_account_is_not_payout_enabled(): void
    {
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'seller']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $seller = User::factory()->create();
        $seller->assignRole('seller');

        ConnectAccount::create([
            'user_id' => $seller->id,
            'stripe_account_id' => 'acct_payee_disabled',
            'type' => 'express',
            'charges_enabled' => true,
            'payouts_enabled' => false,
            'details_submitted' => true,
        ]);

        Sanctum::actingAs($admin);

        $this->app->instance(\Stripe\StripeClient::class, $this->fakeStripeClient());

        $this->postJson("/api/admin/payouts/{$seller->id}/pay")
            ->assertStatus(422);
    }

    public function test_admin_can_resume_transfer_pending_entries(): void
    {
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'seller']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $seller = User::factory()->create();
        $seller->assignRole('seller');

        ConnectAccount::create([
            'user_id' => $seller->id,
            'stripe_account_id' => 'acct_payee_resume',
            'type' => 'express',
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'details_submitted' => true,
        ]);

        $buyer = User::factory()->create();
        $order = Order::create([
            'buyer_id' => $buyer->id,
            'status' => 'paid',
            'currency_iso' => 'EUR',
            'subtotal_amount' => 4000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 4000,
            'transfer_group' => 'order_resume_1',
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'purchasable_type' => 'App\\Models\\Product',
            'purchasable_id' => 1,
            'title_snapshot' => 'Retry Item',
            'quantity' => 1,
            'unit_amount' => 4000,
            'gross_amount' => 4000,
            'platform_fee_amount' => 400,
            'net_amount' => 3600,
            'payee_user_id' => $seller->id,
            'status' => 'pending',
        ]);

        WalletLedgerEntry::create([
            'user_id' => $seller->id,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'type' => 'transfer_pending',
            'amount' => 3600,
            'currency_iso' => 'EUR',
            'available_on' => now()->subDay(),
            'metadata' => [
                'transfer_batch_key' => 'old_batch_key',
            ],
        ]);

        $this->app->instance(\Stripe\StripeClient::class, $this->fakeStripeClient());

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/payouts/{$seller->id}/pay")
            ->assertStatus(200)
            ->assertJsonStructure(['message', 'transfers']);

        $this->assertDatabaseHas('wallet_ledger_entries', [
            'user_id' => $seller->id,
            'order_item_id' => $item->id,
            'type' => 'transfer_out',
        ]);
    }
}
