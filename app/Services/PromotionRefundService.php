<?php

namespace App\Services;

use App\Models\Ad;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Model;
use Stripe\StripeClient;

class PromotionRefundService
{
    public function __construct(private StripeClient $stripe)
    {
    }

    public function refundListingRejection(Listing $listing, string $reason = 'listing_rejected'): ?array
    {
        return $this->refundPromotion($listing, 'listing', $reason);
    }

    public function refundAdRejection(Ad $ad, string $reason = 'ad_rejected'): ?array
    {
        return $this->refundPromotion($ad, 'ad', $reason);
    }

    private function refundPromotion(Model $promotion, string $type, string $reason): ?array
    {
        $metadata = $promotion->metadata ?? [];
        $existingRefund = $metadata['refund'] ?? null;

        if (($promotion->refund_status ?? null) === 'refunded' && is_array($existingRefund)) {
            return $existingRefund;
        }

        $paymentIntentId = $metadata['payment_intent_id'] ?? null;
        $amount = $this->resolveAmount($promotion);

        if (!$paymentIntentId || $amount <= 0) {
            return null;
        }

        $refund = $this->stripe->refunds->create([
            'payment_intent' => $paymentIntentId,
            'amount' => $amount,
            'metadata' => [
                'promotion_type' => $type,
                'promotion_id' => (string) $promotion->getKey(),
                'reason' => $reason,
            ],
        ], [
            'idempotency_key' => $this->makeRefundIdempotencyKey($type, $promotion->getKey()),
        ]);

        $refundData = $refund instanceof \Stripe\StripeObject ? $refund->toArray() : (array) $refund;

        $refundPayload = [
            'applied' => true,
            'status' => (string) ($refundData['status'] ?? 'pending'),
            'amount' => (int) ($refundData['amount'] ?? $amount),
            'currency_iso' => strtoupper((string) ($refundData['currency'] ?? $this->resolveCurrency($promotion))),
            'stripe_refund_id' => $refundData['id'] ?? null,
            'metadata' => $refundData,
        ];

        $metadata['refund'] = $refundPayload;
        $metadata['refund_reason'] = $reason;

        $promotion->forceFill([
            'refund_status' => $refundPayload['status'] === 'failed' ? 'failed' : 'refunded',
            'metadata' => $metadata,
        ])->save();

        return $refundPayload;
    }

    private function resolveAmount(Model $promotion): int
    {
        return (int) round(((float) ($promotion->price ?? 0)) * 100);
    }

    private function resolveCurrency(Model $promotion): string
    {
        if ($promotion instanceof Ad) {
            return (string) config('ads.currency', config('stripe.currency', 'eur'));
        }

        return (string) config('listings.currency', config('stripe.currency', 'eur'));
    }

    private function makeRefundIdempotencyKey(string $type, int|string $id): string
    {
        return $type . '_refund_' . sha1((string) $id);
    }
}
