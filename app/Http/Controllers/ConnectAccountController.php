<?php

namespace App\Http\Controllers;

use App\Models\ConnectAccount;
use Illuminate\Http\Request;
use Stripe\StripeClient;

class ConnectAccountController extends Controller
{
    public function start(Request $request, StripeClient $stripe)
    {
        $user = $request->user();

        if (!$user->hasAnyRole(['seller', 'service_provider', 'admin'])) {
            abort(403, 'Unauthorized.');
        }

        $account = ConnectAccount::where('user_id', $user->id)->first();
        if (!$account) {
            $stripeAccount = $stripe->accounts->create([
                'type' => 'express',
                'country' => config('stripe.connect_country', 'DE'),
                'email' => $user->email,
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
            ]);

            $account = ConnectAccount::create([
                'user_id' => $user->id,
                'stripe_account_id' => $stripeAccount->id,
                'type' => 'express',
                'charges_enabled' => (bool) $stripeAccount->charges_enabled,
                'payouts_enabled' => (bool) $stripeAccount->payouts_enabled,
                'details_submitted' => (bool) $stripeAccount->details_submitted,
                'requirements' => $stripeAccount->requirements ?? null,
            ]);
        }

        $returnUrl = config('stripe.connect_return_url') ?: config('app.url');
        $refreshUrl = config('stripe.connect_refresh_url') ?: config('app.url');

        $link = $stripe->accountLinks->create([
            'account' => $account->stripe_account_id,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);

        return response()->json([
            'onboarding_url' => $link->url,
            'expires_at' => $link->expires_at,
            'account' => [
                'stripe_account_id' => $account->stripe_account_id,
                'charges_enabled' => $account->charges_enabled,
                'payouts_enabled' => $account->payouts_enabled,
                'details_submitted' => $account->details_submitted,
            ],
        ]);
    }

    public function status(Request $request)
    {
        $user = $request->user();

        if (!$user->hasAnyRole(['seller', 'service_provider', 'admin'])) {
            abort(403, 'Unauthorized.');
        }

        $account = ConnectAccount::where('user_id', $user->id)->first();

        return response()->json([
            'connected' => (bool) $account,
            'account' => $account,
        ]);
    }
}
