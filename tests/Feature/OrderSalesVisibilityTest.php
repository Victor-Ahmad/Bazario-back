<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderSalesVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_orders_returns_only_orders_for_authenticated_buyer(): void
    {
        $buyerA = User::factory()->create();
        $buyerB = User::factory()->create();

        $orderA = Order::create([
            'buyer_id' => $buyerA->id,
            'status' => 'paid',
            'currency_iso' => 'EUR',
            'subtotal_amount' => 1500,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 1500,
        ]);

        Order::create([
            'buyer_id' => $buyerB->id,
            'status' => 'paid',
            'currency_iso' => 'EUR',
            'subtotal_amount' => 2200,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 2200,
        ]);

        Sanctum::actingAs($buyerA);

        $response = $this->getJson('/api/orders/my-orders');
        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($orderA->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_my_sales_returns_only_paid_items_for_current_payee(): void
    {
        $payeeA = User::factory()->create();
        $payeeB = User::factory()->create();
        $buyer = User::factory()->create();

        $paidOrder = Order::create([
            'buyer_id' => $buyer->id,
            'status' => 'paid',
            'currency_iso' => 'EUR',
            'subtotal_amount' => 3000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 3000,
        ]);

        $draftOrder = Order::create([
            'buyer_id' => $buyer->id,
            'status' => 'draft',
            'currency_iso' => 'EUR',
            'subtotal_amount' => 1000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 1000,
        ]);

        $visibleItem = OrderItem::create([
            'order_id' => $paidOrder->id,
            'purchasable_type' => 'App\\Models\\Product',
            'purchasable_id' => 1,
            'title_snapshot' => 'Visible item',
            'quantity' => 1,
            'unit_amount' => 3000,
            'gross_amount' => 3000,
            'platform_fee_amount' => 300,
            'net_amount' => 2700,
            'payee_user_id' => $payeeA->id,
            'status' => 'pending',
        ]);

        OrderItem::create([
            'order_id' => $draftOrder->id,
            'purchasable_type' => 'App\\Models\\Product',
            'purchasable_id' => 2,
            'title_snapshot' => 'Draft item',
            'quantity' => 1,
            'unit_amount' => 1000,
            'gross_amount' => 1000,
            'platform_fee_amount' => 100,
            'net_amount' => 900,
            'payee_user_id' => $payeeA->id,
            'status' => 'pending',
        ]);

        OrderItem::create([
            'order_id' => $paidOrder->id,
            'purchasable_type' => 'App\\Models\\Product',
            'purchasable_id' => 3,
            'title_snapshot' => 'Other payee item',
            'quantity' => 1,
            'unit_amount' => 500,
            'gross_amount' => 500,
            'platform_fee_amount' => 50,
            'net_amount' => 450,
            'payee_user_id' => $payeeB->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($payeeA);

        $response = $this->getJson('/api/orders/my-sales');
        $response->assertStatus(200);

        $ids = collect($response->json('items'))->pluck('id')->all();
        $this->assertSame([$visibleItem->id], $ids);
    }
}
