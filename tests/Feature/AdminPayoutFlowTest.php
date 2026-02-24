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
                    public function create(array $params)
                    {
                        return (object) [
                            'id' => 'tr_test_123',
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
            'transfer_id' => 'tr_test_123',
            'amount' => 1800,
        ]);

        $this->assertDatabaseHas('wallet_ledger_entries', [
            'user_id' => $seller->id,
            'order_item_id' => $item->id,
            'type' => 'transfer_out',
        ]);
    }
}
