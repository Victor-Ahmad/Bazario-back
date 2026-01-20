<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ServiceBooking;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        $order = Order::create([
            'buyer_id' => $user->id,
            'status' => 'draft',
            'currency_iso' => 'EUR',
            'transfer_group' => null,
            'subtotal_amount' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
        ]);

        return response()->json($order);
    }

    public function show(Request $request, Order $order)
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);

        return response()->json(
            $order->load(['items', 'items.serviceBooking'])
        );
    }

    public function addItem(Request $request, Order $order)
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);
        abort_if($order->status !== 'draft', 422, __('orders.not_editable'));

        $data = $request->validate([
            'type' => ['required', 'in:product,service,listing'],
            'id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1'],

            // service booking fields (required if type=service)
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'location_type' => ['nullable', 'string', 'max:32'],
            'location_payload' => ['nullable', 'array'],
        ]);

        return DB::transaction(function () use ($order, $data) {
            $qty = (int)($data['quantity'] ?? 1);

            if ($data['type'] === 'product') {
                $product = Product::with('seller')->findOrFail($data['id']);


                $payeeUserId = $product->seller->user_id ?? null;
                abort_if(!$payeeUserId, 422, __('orders.seller_user_missing'));

                $unit = (int) round(((float)$product->price) * 100);
                $gross = $unit * $qty;

                $fee = (int) round($gross * 0.10); //  10% fee 
                $net = $gross - $fee;

                OrderItem::create([
                    'order_id' => $order->id,
                    'purchasable_type' => Product::class,
                    'purchasable_id' => $product->id,
                    'title_snapshot' => is_string($product->name) ? $product->name : null,
                    'quantity' => $qty,
                    'unit_amount' => $unit,
                    'gross_amount' => $gross,
                    'platform_fee_amount' => $fee,
                    'net_amount' => $net,
                    'payee_user_id' => $payeeUserId,
                    'status' => 'pending',
                ]);
            }

            if ($data['type'] === 'listing') {
                $listing = Listing::findOrFail($data['id']);

                $unit = (int) round(((float)$listing->price) * 100);
                $gross = $unit * $qty;
                $fee = (int) round($gross * 0.10);
                $net = $gross - $fee;

                OrderItem::create([
                    'order_id' => $order->id,
                    'purchasable_type' => Listing::class,
                    'purchasable_id' => $listing->id,
                    'title_snapshot' => $listing->title,
                    'description_snapshot' => $listing->description,
                    'quantity' => $qty,
                    'unit_amount' => $unit,
                    'gross_amount' => $gross,
                    'platform_fee_amount' => $fee,
                    'net_amount' => $net,
                    'payee_user_id' => $listing->user_id,
                    'status' => 'pending',
                ]);
            }

            if ($data['type'] === 'service') {
                $service = Service::with('serviceProvider')->findOrFail($data['id']);

                $providerUserId = $service->serviceProvider->user_id ?? null;
                abort_if(!$providerUserId, 422, __('orders.provider_user_missing'));

                // Booking fields required for services
                abort_if(
                    empty($data['starts_at']) || empty($data['ends_at']),
                    422,
                    __('orders.service_dates_required')
                );

                $unit = (int) round(((float)$service->price) * 100);
                $gross = $unit;
                $fee = (int) round($gross * 0.10);
                $net = $gross - $fee;

                $item = OrderItem::create([
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

                ServiceBooking::create([
                    'order_item_id' => $item->id,
                    'service_id' => $service->id,
                    'provider_user_id' => $providerUserId,
                    'status' => 'requested',
                    'starts_at' => $data['starts_at'],
                    'ends_at' => $data['ends_at'],
                    'timezone' => $data['timezone'] ?? null,
                    'location_type' => $data['location_type'] ?? $service->location_type,
                    'location_payload' => $data['location_payload'] ?? null,
                ]);
            }

            $subtotal = (int) OrderItem::where('order_id', $order->id)->sum('gross_amount');
            $order->update([
                'subtotal_amount' => $subtotal,
                'total_amount' => $subtotal,
            ]);

            return response()->json($order->load(['items', 'items.serviceBooking']));
        });
    }
}
