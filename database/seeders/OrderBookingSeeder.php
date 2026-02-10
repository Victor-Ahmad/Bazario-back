<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Product;
use App\Models\Service;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ServiceBooking;
use App\Models\StripePayment;
use App\Models\WalletLedgerEntry;
use Carbon\Carbon;

class OrderBookingSeeder extends Seeder
{
    public function run(): void
    {
        $customerUsers = User::whereIn('email', [
            'yara.customer@example.com',
            'hadi.customer@example.com',
        ])->get();

        $products = Product::query()->take(6)->get();
        $services = Service::query()->with('serviceProvider')->take(4)->get();

        if ($customerUsers->isEmpty() || $products->isEmpty() || $services->isEmpty()) {
            return;
        }

        $feeRate = 0.10;

        foreach ($customerUsers as $buyer) {
            $order = Order::create([
                'buyer_id' => $buyer->id,
                'status' => 'paid',
                'currency_iso' => 'EUR',
                'subtotal_amount' => 0,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => 0,
                'placed_at' => now()->subDays(2),
                'paid_at' => now()->subDays(2),
                'transfer_group' => null,
            ]);

            $orderItems = [];

            $productSample = $products->random(2);
            foreach ($productSample as $product) {
                $qty = random_int(1, 3);
                $unit = (int) round(((float) $product->price) * 100);
                $gross = $unit * $qty;
                $fee = (int) round($gross * $feeRate);
                $net = $gross - $fee;

                $item = OrderItem::create([
                    'order_id' => $order->id,
                    'purchasable_type' => Product::class,
                    'purchasable_id' => $product->id,
                    'title_snapshot' => is_string($product->name) ? $product->name : null,
                    'description_snapshot' => is_string($product->description) ? $product->description : null,
                    'quantity' => $qty,
                    'unit_amount' => $unit,
                    'gross_amount' => $gross,
                    'platform_fee_amount' => $fee,
                    'net_amount' => $net,
                    'payee_user_id' => $product->seller?->user_id ?? $buyer->id,
                    'status' => 'pending',
                ]);

                $orderItems[] = $item;
            }

            $service = $services->random();
            $providerUserId = $service->serviceProvider?->user_id;
            if ($providerUserId) {
                $unit = (int) round(((float) $service->price) * 100);
                $gross = $unit;
                $fee = (int) round($gross * $feeRate);
                $net = $gross - $fee;

                $serviceItem = OrderItem::create([
                    'order_id' => $order->id,
                    'purchasable_type' => Service::class,
                    'purchasable_id' => $service->id,
                    'title_snapshot' => is_string($service->title) ? $service->title : null,
                    'description_snapshot' => is_string($service->description) ? $service->description : null,
                    'quantity' => 1,
                    'unit_amount' => $unit,
                    'gross_amount' => $gross,
                    'platform_fee_amount' => $fee,
                    'net_amount' => $net,
                    'payee_user_id' => $providerUserId,
                    'status' => 'pending',
                ]);

                $orderItems[] = $serviceItem;

                $startsAtUtc = Carbon::now('UTC')->addDays(random_int(2, 7))->setTime(10, 0);
                $duration = (int) ($service->duration_minutes ?? 60);
                $endsAtUtc = $startsAtUtc->copy()->addMinutes($duration);

                ServiceBooking::create([
                    'order_item_id' => $serviceItem->id,
                    'service_id' => $service->id,
                    'provider_user_id' => $providerUserId,
                    'customer_user_id' => $buyer->id,
                    'status' => 'confirmed',
                    'starts_at' => $startsAtUtc,
                    'ends_at' => $endsAtUtc,
                    'timezone' => $service->serviceProvider?->timezone ?? 'UTC',
                    'location_type' => $service->location_type,
                    'location_payload' => null,
                ]);
            }

            $subtotal = (int) collect($orderItems)->sum('gross_amount');
            $order->update([
                'subtotal_amount' => $subtotal,
                'total_amount' => $subtotal,
                'transfer_group' => 'order_' . $order->id,
            ]);

            StripePayment::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'payment_intent_id' => 'pi_seed_' . $order->id,
                    'status' => 'succeeded',
                    'amount' => $order->total_amount,
                    'currency_iso' => $order->currency_iso,
                ]
            );

            foreach ($orderItems as $item) {
                WalletLedgerEntry::create([
                    'user_id' => $item->payee_user_id,
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'type' => 'sale_pending',
                    'amount' => (int) $item->net_amount,
                    'currency_iso' => $order->currency_iso,
                    'available_on' => now()->subDay(),
                    'metadata' => [
                        'gross_amount' => (int) $item->gross_amount,
                        'platform_fee_amount' => (int) $item->platform_fee_amount,
                    ],
                ]);
            }
        }
    }
}
