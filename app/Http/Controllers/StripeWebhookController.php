<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessStripeEventJob;
use App\Models\StripeWebhookEvent;
use Illuminate\Http\Request;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent(); // RAW body (important!)
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                config('stripe.webhook_secret')
            );
        } catch (\Throwable $e) {
            return response('Invalid webhook signature', Response::HTTP_BAD_REQUEST);
        }

        // Idempotency at the "event received" level:
        $row = StripeWebhookEvent::firstOrCreate(
            ['event_id' => $event->id],
            ['type' => $event->type]
        );

        // Already processed -> acknowledge fast
        if ($row->processed_at) {
            return response('OK', 200);
        }

        // Queue processing (recommended)
        ProcessStripeEventJob::dispatch($event->toArray());

        return response('OK', 200);
    }
}
