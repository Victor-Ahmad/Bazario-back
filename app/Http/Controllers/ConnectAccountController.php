<?php

namespace App\Http\Controllers;

use App\Models\ConnectAccount;
use App\Models\Listing;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\StripeTransfer;
use App\Models\WalletLedgerEntry;
use Illuminate\Http\Request;
use Stripe\StripeClient;

class ConnectAccountController extends Controller
{
    public function start(Request $request, StripeClient $stripe)
    {
        $user = $request->user();
        $eligibility = $this->resolveEligibility($request);

        if (!$eligibility['allowed']) {
            abort(403, 'Unauthorized.');
        }

        if (!$user->email) {
            abort(422, 'User must have an email address before starting Stripe onboarding.');
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
        } else {
            $account = $this->syncAccountFromStripe($account, $stripe);
        }

        [$returnUrl, $refreshUrl] = $this->resolveAccountLinkUrls($user);

        $link = $stripe->accountLinks->create([
            'account' => $account->stripe_account_id,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);

        return response()->json([
            'onboarding_url' => $link->url,
            'expires_at' => $link->expires_at,
            'eligible_type' => $eligibility['type'],
            'account' => [
                'stripe_account_id' => $account->stripe_account_id,
                'charges_enabled' => $account->charges_enabled,
                'payouts_enabled' => $account->payouts_enabled,
                'details_submitted' => $account->details_submitted,
            ],
        ]);
    }

    public function status(Request $request, StripeClient $stripe)
    {
        $eligibility = $this->resolveEligibility($request);

        if (!$eligibility['allowed']) {
            abort(403, 'Unauthorized.');
        }

        $account = ConnectAccount::where('user_id', $request->user()->id)->first();
        if ($account) {
            $account = $this->syncAccountFromStripe($account, $stripe);
        }

        return response()->json([
            'connected' => (bool) $account,
            'eligible' => $eligibility['allowed'],
            'eligible_type' => $eligibility['type'],
            'account' => $account,
        ]);
    }

    public function summary(Request $request, StripeClient $stripe)
    {
        $eligibility = $this->resolveEligibility($request);

        if (!$eligibility['allowed']) {
            abort(403, 'Unauthorized.');
        }

        $user = $request->user();
        $account = ConnectAccount::where('user_id', $user->id)->first();
        if ($account) {
            $account = $this->syncAccountFromStripe($account, $stripe);
        }

        $stripeBalance = [
            'available' => [],
            'pending' => [],
        ];

        if ($account) {
            try {
                $balance = $stripe->balance->retrieve([], [
                    'stripe_account' => $account->stripe_account_id,
                ]);

                $stripeBalance = [
                    'available' => $this->normalizeBalanceRows($balance->available ?? []),
                    'pending' => $this->normalizeBalanceRows($balance->pending ?? []),
                ];
            } catch (\Throwable $e) {
                // Keep summary usable even if live Stripe balance cannot be fetched.
            }
        }

        $platformPendingEntries = WalletLedgerEntry::query()
            ->where('user_id', $user->id)
            ->whereIn('type', ['sale_pending', 'transfer_pending'])
            ->where(function ($q) {
                $q->whereNull('available_on')->orWhere('available_on', '<=', now());
            })
            ->with('orderItem')
            ->get()
            ->values();

        $platformPendingSummary = $this->summarizePendingBalances($platformPendingEntries);

        $transferModels = StripeTransfer::query()
            ->where('payee_user_id', $user->id)
            ->with('order.items')
            ->latest()
            ->limit(25)
            ->get()
            ->values();

        $transferSummary = $this->summarizeTransfers($transferModels);

        return response()->json([
            'eligible' => $eligibility['allowed'],
            'eligible_type' => $eligibility['type'],
            'connected' => (bool) $account,
            'account' => $account,
            'stripe_balance' => $stripeBalance,
            'platform_pending_balance' => $platformPendingSummary['all'],
            'transfers' => $transferSummary['all'],
            'earnings_by_role' => [
                'seller' => [
                    'platform_pending_balance' => $platformPendingSummary['roles']['seller'],
                    'transfers' => $transferSummary['roles']['seller'],
                ],
                'service_provider' => [
                    'platform_pending_balance' => $platformPendingSummary['roles']['service_provider'],
                    'transfers' => $transferSummary['roles']['service_provider'],
                ],
            ],
        ]);
    }

    private function resolveEligibility(Request $request): array
    {
        $user = $request->user();
        $requestedType = (string) ($request->input('account_type') ?: $request->query('account_type') ?: '');

        if ($user->hasRole('admin')) {
            return ['allowed' => true, 'type' => $requestedType ?: 'admin'];
        }

        if ($user->hasRole('seller')) {
            return ['allowed' => true, 'type' => 'seller'];
        }

        if ($user->hasRole('service_provider')) {
            return ['allowed' => true, 'type' => 'service_provider'];
        }

        if ($requestedType === 'seller') {
            $seller = Seller::query()->where('user_id', $user->id)->first();
            if ($seller) {
                return ['allowed' => true, 'type' => 'seller'];
            }
        }

        if ($requestedType === 'service_provider') {
            $provider = ServiceProvider::query()->where('user_id', $user->id)->first();
            if ($provider) {
                return ['allowed' => true, 'type' => 'service_provider'];
            }
        }

        if ($requestedType === '') {
            if (Seller::query()->where('user_id', $user->id)->exists()) {
                return ['allowed' => true, 'type' => 'seller'];
            }

            if (ServiceProvider::query()->where('user_id', $user->id)->exists()) {
                return ['allowed' => true, 'type' => 'service_provider'];
            }
        }

        return ['allowed' => false, 'type' => null];
    }

    private function resolveAccountLinkUrls($user): array
    {
        $frontendUrl = rtrim((string) (config('stripe.frontend_url') ?: config('app.url')), '/');
        $defaultAccountUrl = $frontendUrl . '/account/stripe';

        if ($user->hasRole('admin')) {
            return [
                config('stripe.connect_return_url') ?: $defaultAccountUrl,
                config('stripe.connect_refresh_url') ?: $defaultAccountUrl,
            ];
        }

        return [
            config('stripe.connect_return_url') ?: $defaultAccountUrl,
            config('stripe.connect_refresh_url') ?: $defaultAccountUrl,
        ];
    }

    private function syncAccountFromStripe(ConnectAccount $account, StripeClient $stripe): ConnectAccount
    {
        try {
            $stripeAccount = $stripe->accounts->retrieve($account->stripe_account_id, []);
        } catch (\Throwable $e) {
            return $account;
        }

        $account->fill([
            'charges_enabled' => (bool) ($stripeAccount->charges_enabled ?? $account->charges_enabled),
            'payouts_enabled' => (bool) ($stripeAccount->payouts_enabled ?? $account->payouts_enabled),
            'details_submitted' => (bool) ($stripeAccount->details_submitted ?? $account->details_submitted),
            'requirements' => $stripeAccount->requirements ?? $account->requirements,
            'onboarding_completed_at' => (
                (bool) ($stripeAccount->details_submitted ?? false)
                && !$account->onboarding_completed_at
            ) ? now() : $account->onboarding_completed_at,
        ]);

        if ($account->isDirty()) {
            $account->save();
        }

        return $account->fresh() ?? $account;
    }

    private function normalizeBalanceRows(iterable $rows): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            $normalized[] = [
                'currency_iso' => strtoupper((string) ($row->currency ?? 'EUR')),
                'amount' => (int) ($row->amount ?? 0),
            ];
        }

        return $normalized;
    }

    private function summarizePendingBalances(iterable $entries): array
    {
        $all = [];
        $roles = [
            'seller' => [],
            'service_provider' => [],
        ];

        foreach ($entries as $entry) {
            $currencyIso = strtoupper((string) $entry->currency_iso);
            $amount = (int) $entry->amount;

            $all[$currencyIso] = ($all[$currencyIso] ?? 0) + $amount;

            $earningRole = $this->resolveLedgerEntryRole($entry);
            if ($earningRole) {
                $roles[$earningRole][$currencyIso] = ($roles[$earningRole][$currencyIso] ?? 0) + $amount;
            }
        }

        return [
            'all' => $this->formatGroupedBalances($all),
            'roles' => [
                'seller' => $this->formatGroupedBalances($roles['seller']),
                'service_provider' => $this->formatGroupedBalances($roles['service_provider']),
            ],
        ];
    }

    private function summarizeTransfers(iterable $transfers): array
    {
        $all = [];
        $roles = [
            'seller' => [],
            'service_provider' => [],
        ];

        foreach ($transfers as $transfer) {
            $normalized = [
                'id' => $transfer->id,
                'order_id' => $transfer->order_id,
                'transfer_id' => $transfer->transfer_id,
                'amount' => (int) $transfer->amount,
                'currency_iso' => strtoupper((string) $transfer->currency_iso),
                'status' => $transfer->status,
                'created_at' => optional($transfer->created_at)?->toISOString(),
                'earning_role' => $this->resolveTransferRole($transfer),
            ];

            $all[] = $normalized;

            if ($normalized['earning_role']) {
                $roles[$normalized['earning_role']][] = $normalized;
            }
        }

        return [
            'all' => $all,
            'roles' => $roles,
        ];
    }

    private function formatGroupedBalances(array $totals): array
    {
        $rows = [];

        foreach ($totals as $currencyIso => $amount) {
            $rows[] = [
                'currency_iso' => $currencyIso,
                'amount' => (int) $amount,
            ];
        }

        return array_values($rows);
    }

    private function resolveLedgerEntryRole(WalletLedgerEntry $entry): ?string
    {
        $metadataRole = $entry->metadata['earning_role'] ?? null;
        if (is_string($metadataRole) && in_array($metadataRole, ['seller', 'service_provider'], true)) {
            return $metadataRole;
        }

        return match ($entry->orderItem?->purchasable_type) {
            Product::class, Listing::class => 'seller',
            Service::class => 'service_provider',
            default => null,
        };
    }

    private function resolveTransferRole(StripeTransfer $transfer): ?string
    {
        $metadataRole = $transfer->metadata['earning_role'] ?? null;
        if (is_string($metadataRole) && in_array($metadataRole, ['seller', 'service_provider'], true)) {
            return $metadataRole;
        }

        $roles = [];
        foreach ($transfer->order?->items ?? [] as $item) {
            $role = match ($item->purchasable_type) {
                Product::class, Listing::class => 'seller',
                Service::class => 'service_provider',
                default => null,
            };

            if ($role) {
                $roles[$role] = true;
            }
        }

        if (count($roles) === 1) {
            return array_key_first($roles);
        }

        return null;
    }
}
