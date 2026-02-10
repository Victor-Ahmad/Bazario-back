<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\StripeTransfer;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;

class PayoutController extends Controller
{
    public function index()
    {
        $users = User::query()
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', ['seller', 'service_provider']);
            })
            ->with(['connectAccount', 'roles'])
            ->get(['id', 'name', 'email']);

        $balances = WalletLedgerEntry::query()
            ->select('user_id', 'currency_iso', DB::raw('SUM(amount) as amount'))
            ->where('type', 'sale_pending')
            ->where(function ($q) {
                $q->whereNull('available_on')->orWhere('available_on', '<=', now());
            })
            ->groupBy('user_id', 'currency_iso')
            ->get();

        $balanceMap = [];
        foreach ($balances as $row) {
            $balanceMap[$row->user_id][] = [
                'currency_iso' => $row->currency_iso,
                'amount' => (int) $row->amount,
            ];
        }

        $data = $users->map(function ($user) use ($balanceMap) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->values(),
                'balances' => $balanceMap[$user->id] ?? [],
                'connect_account' => $user->connectAccount,
            ];
        });

        return response()->json([
            'result' => $data,
        ]);
    }

    public function payUser(Request $request, User $user, StripeClient $stripe)
    {
        $user->load('connectAccount');
        $account = $user->connectAccount;
        if (!$account) {
            abort(422, 'User does not have a connected account.');
        }

        if (!$account->payouts_enabled) {
            abort(422, 'Connected account is not payout-enabled.');
        }

        $entries = WalletLedgerEntry::query()
            ->where('user_id', $user->id)
            ->where('type', 'sale_pending')
            ->where(function ($q) {
                $q->whereNull('available_on')->orWhere('available_on', '<=', now());
            })
            ->get();

        if ($entries->isEmpty()) {
            abort(422, 'No available balance to payout.');
        }

        $groups = $entries->groupBy(function ($entry) {
            return $entry->order_id . '|' . $entry->currency_iso;
        });

        $results = [];

        DB::transaction(function () use ($groups, $stripe, $account, $user, &$results) {
            foreach ($groups as $groupKey => $groupEntries) {
                $sample = $groupEntries->first();
                if (!$sample?->order_id) {
                    continue;
                }

                $amount = (int) $groupEntries->sum('amount');
                if ($amount <= 0) {
                    continue;
                }

                $order = Order::query()->find($sample->order_id);
                $transferGroup = $order?->transfer_group ?: ('order_' . $sample->order_id);

                $transfer = $stripe->transfers->create([
                    'amount' => $amount,
                    'currency' => strtolower($sample->currency_iso),
                    'destination' => $account->stripe_account_id,
                    'transfer_group' => $transferGroup,
                ]);

                StripeTransfer::create([
                    'order_id' => $sample->order_id,
                    'payee_user_id' => $user->id,
                    'transfer_id' => $transfer->id,
                    'amount' => $amount,
                    'currency_iso' => strtoupper($sample->currency_iso),
                    'status' => $transfer->status ?? 'created',
                    'metadata' => [
                        'transfer_group' => $transferGroup,
                    ],
                ]);

                foreach ($groupEntries as $entry) {
                    $meta = $entry->metadata ?? [];
                    $meta['transfer_id'] = $transfer->id;
                    $entry->update([
                        'type' => 'transfer_out',
                        'metadata' => $meta,
                    ]);
                }

                $results[] = [
                    'order_id' => $sample->order_id,
                    'transfer_id' => $transfer->id,
                    'amount' => $amount,
                    'currency_iso' => strtoupper($sample->currency_iso),
                ];
            }
        });

        return response()->json([
            'message' => 'Payout initiated.',
            'transfers' => $results,
        ]);
    }

    public function payAll(Request $request, StripeClient $stripe)
    {
        $users = User::query()
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', ['seller', 'service_provider']);
            })
            ->get();

        $processed = [];
        foreach ($users as $user) {
            try {
                $result = $this->payUser($request, $user, $stripe)->getData(true);
                $processed[] = [
                    'user_id' => $user->id,
                    'status' => 'ok',
                    'transfers' => $result['transfers'] ?? [],
                ];
            } catch (\Throwable $e) {
                $processed[] = [
                    'user_id' => $user->id,
                    'status' => 'skipped',
                    'reason' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => 'Payout batch completed.',
            'results' => $processed,
        ]);
    }
}
