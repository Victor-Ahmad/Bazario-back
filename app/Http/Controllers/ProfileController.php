<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ServiceBooking;
use App\Support\MediaPath;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    use ApiResponseTrait;

    private function syncApprovedBusinessRoles(User $user): void
    {
        $user->loadMissing(['seller', 'serviceProvider', 'roles']);

        $preservedRoles = $user->getRoleNames()
            ->filter(fn(string $roleName) => !in_array($roleName, ['customer', 'seller', 'service_provider'], true))
            ->values()
            ->all();

        $nextRoles = [...$preservedRoles, 'customer'];

        if ($user->seller?->status === 'approved') {
            $nextRoles[] = 'seller';
        }

        if ($user->serviceProvider?->status === 'approved') {
            $nextRoles[] = 'service_provider';
        }

        $resolvedRoles = array_values(array_unique($nextRoles));
        $currentRoles = $user->getRoleNames()->values()->all();

        sort($resolvedRoles);
        sort($currentRoles);

        if ($resolvedRoles !== $currentRoles) {
            $user->syncRoles($resolvedRoles);
            $user->load('roles');
        }
    }

    public function show(Request $request)
    {
        $user = $request->user()->load([
            'seller.attachments',
            'serviceProvider.attachments',
        ]);

        $this->syncApprovedBusinessRoles($user);

        $result = [
            'user' => $this->serializeUser($user),
        ];

        if ($this->shouldIncludeSummary($request)) {
            $result += $this->buildSummary($user->id, $request);
        }

        return $this->successResponse($result, 'auth', 'fetched_successfully');
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => [
                'nullable', 'string', 'max:255', Rule::unique('users', 'phone')->ignore($user->id),
            ],
            'age' => ['nullable', 'integer', 'min:12', 'max:100'],
            'seller_logo' => ['nullable', 'image', 'max:4096'],
            'service_provider_logo' => ['nullable', 'image', 'max:4096'],
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?: null,
            'age' => $data['age'] ?? null,
        ]);

        $disk = MediaPath::uploadsDisk();

        if ($request->hasFile('seller_logo') && $user->seller) {
            $oldLogo = MediaPath::normalizeStoredPath((string) $user->seller->getRawOriginal('logo'));
            $newLogo = $request->file('seller_logo')->store('sellers/logos', $disk);
            $user->seller->update(['logo' => $newLogo]);

            if ($oldLogo) {
                Storage::disk($disk)->delete($oldLogo);
            }
        }

        if ($request->hasFile('service_provider_logo') && $user->serviceProvider) {
            $oldLogo = MediaPath::normalizeStoredPath((string) $user->serviceProvider->getRawOriginal('logo'));
            $newLogo = $request->file('service_provider_logo')->store('service_providers/logos', $disk);
            $user->serviceProvider->update(['logo' => $newLogo]);

            if ($oldLogo) {
                Storage::disk($disk)->delete($oldLogo);
            }
        }

        $user->load([
            'seller.attachments',
            'serviceProvider.attachments',
        ]);

        $this->syncApprovedBusinessRoles($user);

        return $this->successResponse([
            'user' => $this->serializeUser($user),
        ], 'auth', 'updated_successfully');
    }

    private function buildSummary(int $userId, Request $request): array
    {
        $limit = max(1, min((int) $request->integer('limit', 5), 10));

        $recentOrders = Order::query()
            ->where('buyer_id', $userId)
            ->where('status', '!=', 'draft')
            ->with(['items', 'items.serviceBooking'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $recentBookings = ServiceBooking::query()
            ->where('customer_user_id', $userId)
            ->where(function ($query) {
                $query->whereNull('order_item_id')
                    ->orWhereHas('orderItem.order', function ($orderQuery) {
                        $orderQuery->whereIn('status', ['paid', 'partially_refunded', 'refunded']);
                    });
            })
            ->with(['service', 'providerUser:id,name,email,phone'])
            ->orderByDesc('starts_at')
            ->limit($limit)
            ->get();

        $recentSales = OrderItem::query()
            ->where('payee_user_id', $userId)
            ->whereHas('order', function ($query) {
                $query->where('status', 'paid');
            })
            ->with(['order.buyer:id,name,email,phone', 'serviceBooking'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $recentProviderBookings = ServiceBooking::query()
            ->where('provider_user_id', $userId)
            ->where(function ($query) {
                $query->whereNull('order_item_id')
                    ->orWhereHas('orderItem.order', function ($orderQuery) {
                        $orderQuery->whereIn('status', ['paid', 'partially_refunded', 'refunded']);
                    });
            })
            ->with(['service', 'customerUser:id,name,email,phone'])
            ->orderByDesc('starts_at')
            ->limit($limit)
            ->get();

        return [
            'counts' => [
                'orders' => Order::query()
                    ->where('buyer_id', $userId)
                    ->where('status', '!=', 'draft')
                    ->count(),
                'bookings' => ServiceBooking::query()
                    ->where('customer_user_id', $userId)
                    ->where(function ($query) {
                        $query->whereNull('order_item_id')
                            ->orWhereHas('orderItem.order', function ($orderQuery) {
                                $orderQuery->whereIn('status', ['paid', 'partially_refunded', 'refunded']);
                            });
                    })
                    ->count(),
                'sales' => OrderItem::query()
                    ->where('payee_user_id', $userId)
                    ->whereHas('order', function ($query) {
                        $query->where('status', 'paid');
                    })
                    ->count(),
                'provider_bookings' => ServiceBooking::query()
                    ->where('provider_user_id', $userId)
                    ->where(function ($query) {
                        $query->whereNull('order_item_id')
                            ->orWhereHas('orderItem.order', function ($orderQuery) {
                                $orderQuery->whereIn('status', ['paid', 'partially_refunded', 'refunded']);
                            });
                    })
                    ->count(),
            ],
            'recent_orders' => $recentOrders,
            'recent_bookings' => $recentBookings,
            'recent_sales' => $recentSales,
            'recent_provider_bookings' => $recentProviderBookings,
        ];
    }

    private function shouldIncludeSummary(Request $request): bool
    {
        $include = $request->query('include');

        if (is_array($include)) {
            return in_array('summary', $include, true);
        }

        if (is_string($include)) {
            return collect(explode(',', $include))
                ->map(fn(string $value) => trim($value))
                ->contains('summary');
        }

        return false;
    }

    private function serializeUser($user): array
    {
        $sellerProfile = $user->seller;
        $serviceProviderProfile = $user->serviceProvider;

        $sellerStatus = $sellerProfile?->status;
        $serviceProviderStatus = $serviceProviderProfile?->status;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'age' => $user->age,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'roles' => $user->getRoleNames()->values(),
            'seller_profile' => $sellerStatus === 'approved' ? $sellerProfile : null,
            'service_provider_profile' => $serviceProviderStatus === 'approved' ? $serviceProviderProfile : null,
            'upgrade_requests' => [
                'seller' => $sellerStatus === 'pending' ? 'pending' : null,
                'service_provider' => $serviceProviderStatus === 'pending' ? 'pending' : null,
            ],
            'available_upgrades' => [
                'seller' => $sellerProfile === null || $sellerStatus === 'rejected',
                'service_provider' => $serviceProviderProfile === null || $serviceProviderStatus === 'rejected',
            ],
        ];
    }
}
