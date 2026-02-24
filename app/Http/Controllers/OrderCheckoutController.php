<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\StripePayment;
use Illuminate\Http\Request;
use Stripe\StripeClient;
use Illuminate\Support\Facades\DB;

class OrderCheckoutController extends Controller
{
    public function createPaymentIntent(Request $request, Order $order, StripeClient $stripe)
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);
        abort_if($order->status !== 'draft', 422, __('orders.not_payable'));
        abort_if($order->total_amount <= 0, 422, __('orders.total_invalid'));

        return DB::transaction(function () use ($order, $stripe) {
            $order->update([
                'status' => 'requires_payment',
                'placed_at' => now(),
                'transfer_group' => $order->transfer_group ?: ('order_' . $order->id),
            ]);

            // Idempotency key prevents duplicate PaymentIntents
            $idempotencyKey = 'pi_' . $order->id;

            $pi = $stripe->paymentIntents->create([
                'amount' => $order->total_amount,
                'currency' => strtolower($order->currency_iso),
                'automatic_payment_methods' => ['enabled' => true],
                'transfer_group' => $order->transfer_group,
                'metadata' => [
                    'order_id' => (string) $order->id,
                ],
            ], [
                'idempotency_key' => $idempotencyKey,
            ]);

            StripePayment::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'payment_intent_id' => $pi->id,
                    'status' => $pi->status,
                    'amount' => $order->total_amount,
                    'currency_iso' => $order->currency_iso,
                ]
            );

            return response()->json([
                'client_secret' => $pi->client_secret,
                'payment_intent_id' => $pi->id,
            ]);
        });
    }

    public function createCheckoutSession(Request $request, Order $order, StripeClient $stripe)
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);
        abort_if($order->status !== 'draft', 422, __('orders.not_payable'));
        abort_if($order->total_amount <= 0, 422, __('orders.total_invalid'));

        $order->load(['items', 'buyer']);
        abort_if($order->items->isEmpty(), 422, __('orders.total_invalid'));

        return DB::transaction(function () use ($order, $stripe) {
            $order->update([
                'status' => 'requires_payment',
                'placed_at' => now(),
                'transfer_group' => $order->transfer_group ?: ('order_' . $order->id),
            ]);

            $lineItems = $order->items->map(function ($item) {
                return [
                    'price_data' => [
                        'currency' => strtolower($item->order->currency_iso ?? config('stripe.currency', 'eur')),
                        'product_data' => [
                            'name' => $item->title_snapshot ?: ('Item #' . $item->id),
                        ],
                        'unit_amount' => (int) $item->unit_amount,
                    ],
                    'quantity' => (int) $item->quantity,
                ];
            })->values()->all();

            $successUrl = (config('stripe.checkout_success_url') ?: (config('app.url') . '/demo/order-success.html'))
                . '?order_id=' . $order->id;
            $cancelUrl = config('stripe.checkout_cancel_url') ?: (config('app.url') . '/demo/cart.html');

            $session = $stripe->checkout->sessions->create([
                'mode' => 'payment',
                'line_items' => $lineItems,
                'customer_email' => $order->buyer?->email,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'payment_intent_data' => [
                    'transfer_group' => $order->transfer_group,
                    'metadata' => [
                        'order_id' => (string) $order->id,
                    ],
                ],
                'metadata' => [
                    'order_id' => (string) $order->id,
                ],
            ], [
                'idempotency_key' => 'cs_' . $order->id,
            ]);

            return response()->json([
                'checkout_url' => $session->url,
                'checkout_session_id' => $session->id,
            ]);
        });
    }
}
