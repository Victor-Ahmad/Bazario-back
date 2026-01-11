<?php

namespace App\Jobs;

use App\Models\ConnectAccount;
use App\Models\Order;
use App\Models\StripePayment;
use App\Models\StripeWebhookEvent;
use App\Models\WalletLedgerEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessStripeEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $event;

    public function __construct(array $event)
    {
        $this->event = $event;
    }

    public function handle(): void
    {
        $eventId = $this->event['id'] ?? null;
        $eventType = $this->event['type'] ?? null;

        if (!$eventId || !$eventType) {
            return;
        }

        DB::transaction(function () use ($eventId, $eventType) {
            // Lock event row to avoid double processing in race conditions
            $row = StripeWebhookEvent::where('event_id', $eventId)->lockForUpdate()->first();

            if (!$row || $row->processed_at) {
                return;
            }

            match ($eventType) {
                'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded(),
                'payment_intent.payment_failed' => $this->handlePaymentIntentFailed(),
                'account.updated' => $this->handleAccountUpdated(),
                default => null,
            };

            $row->processed_at = now();
            $row->save();
        });
    }

    private function handlePaymentIntentSucceeded(): void
    {
        $pi = $this->event['data']['object'] ?? null;
        if (!$pi) return;

        $orderId = $pi['metadata']['order_id'] ?? null;
        if (!$orderId) return;

        /** @var Order|null $order */
        $order = Order::with('items')->whereKey($orderId)->lockForUpdate()->first();
        if (!$order) return;

        // Update StripePayment row
        StripePayment::where('order_id', $order->id)->update([
            'status' => $pi['status'] ?? 'succeeded',
            'charge_id' => $pi['latest_charge'] ?? null,
        ]);

        // Idempotency: if already marked paid, don't re-create ledger
        if ($order->paid_at) {
            return;
        }

        $order->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Create "pending wallet" ledger credits per order item
        // (per item is better than aggregated, for refunds/cancellations)
        foreach ($order->items as $item) {
            // If you only want to credit after fulfillment, do NOT credit here.
            // In your model (admin releases later), credit pending here is good.

            $exists = WalletLedgerEntry::where('order_item_id', $item->id)
                ->where('type', 'sale_pending')
                ->exists();

            if ($exists) {
                continue;
            }

            WalletLedgerEntry::create([
                'user_id' => $item->payee_user_id,
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'type' => 'sale_pending',
                'amount' => (int) $item->net_amount, // + credit
                'currency_iso' => $order->currency_iso,
                // optional hold window:
                // 'available_on' => now()->addDays(7),
                'metadata' => [
                    'payment_intent_id' => $pi['id'] ?? null,
                    'gross_amount' => (int) $item->gross_amount,
                    'platform_fee_amount' => (int) $item->platform_fee_amount,
                ],
            ]);
        }
    }

    private function handlePaymentIntentFailed(): void
    {
        $pi = $this->event['data']['object'] ?? null;
        if (!$pi) return;

        $orderId = $pi['metadata']['order_id'] ?? null;
        if (!$orderId) return;

        /** @var Order|null $order */
        $order = Order::whereKey($orderId)->lockForUpdate()->first();
        if (!$order) return;

        // Update payment status
        StripePayment::where('order_id', $order->id)->update([
            'status' => $pi['status'] ?? 'failed',
        ]);

        // Keep order editable or set back to requires_payment
        if (!$order->paid_at) {
            $order->update([
                'status' => 'requires_payment',
            ]);
        }
    }

    private function handleAccountUpdated(): void
    {
        $acct = $this->event['data']['object'] ?? null;
        if (!$acct) return;

        $stripeAccountId = $acct['id'] ?? null;
        if (!$stripeAccountId) return;

        $chargesEnabled = (bool)($acct['charges_enabled'] ?? false);
        $payoutsEnabled = (bool)($acct['payouts_enabled'] ?? false);
        $detailsSubmitted = (bool)($acct['details_submitted'] ?? false);

        ConnectAccount::where('stripe_account_id', $stripeAccountId)->update([
            'charges_enabled' => $chargesEnabled,
            'payouts_enabled' => $payoutsEnabled,
            'details_submitted' => $detailsSubmitted,
            'onboarding_completed_at' => ($chargesEnabled && $payoutsEnabled) ? now() : null,
            'requirements' => $acct['requirements'] ?? null,
        ]);
    }
}
