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
}
