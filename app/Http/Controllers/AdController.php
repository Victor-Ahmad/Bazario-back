<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\AdImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\Service;
use App\Models\Seller;
use App\Models\ServiceProvider;
use App\Models\Listing;
use App\Models\AdPosition;
use App\Services\PromotionRefundService;
use App\Support\MediaPath;
use Illuminate\Support\Str;
use Stripe\StripeClient;


class AdController extends Controller
{
    private const ALLOWED_ADABLES = [
        'product' => \App\Models\Product::class,
        'service' => \App\Models\Service::class,
        'seller'  => \App\Models\Seller::class,
        'service_provider'  => \App\Models\ServiceProvider::class,
    ];

    public function index()
    {
        $ads = $this->publicAdsQuery()
            ->paginate(20);

        $this->loadAdableRelations($ads->getCollection());

        return response()->json(['success' => 1, 'result' => $ads]);
    }
    public function goldIndex()
    {
        return $this->indexByPositionName('golden_ad');
    }

    public function silverIndex()
    {
        return $this->indexByPositionName('silver_ad');
    }

    public function normalIndex()
    {
        return $this->indexByPositionName('normal_ad');
    }
    private function indexByPositionName(string $name)
    {
        $ads = $this->publicAdsQuery()
            ->whereHas('position', function ($q) use ($name) {
                $q->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);
            })
            ->paginate(20);

        $this->loadAdableRelations($ads->getCollection());

        return response()->json(['success' => 1, 'result' => $ads]);
    }

    public function announcements()
    {
        $ads = Listing::approved()
            ->whereHas('user')
            ->with(['user:id,name', 'images', 'coverImage'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => 1, 'result' => $ads]);
    }

    public function positions()
    {
        $positions = AdPosition::query()
            ->orderBy('priority')
            ->get(['id', 'name', 'label', 'priority'])
            ->map(function (AdPosition $position) {
                $pricing = $this->getPricingForPosition($position->name);

                return [
                    'id' => $position->id,
                    'name' => $position->name,
                    'label' => $position->label,
                    'priority' => $position->priority,
                    'tier' => $pricing['tier'],
                    'price' => $pricing['price'],
                    'currency_iso' => strtoupper(config('ads.currency', config('stripe.currency', 'eur'))),
                ];
            })
            ->values();

        return response()->json(['success' => 1, 'result' => $positions]);
    }
    public function getGeneralAds()
    {
        $ads = Ad::with(['images', 'position', 'adable'])
            ->where('status', 'approved')
            ->where('adable_type', '!=', Listing::class)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => 1, 'result' => $ads]);
    }
    public function getPendingAds()
    {
        $ads = Ad::with(['images', 'position', 'adable'])
            ->whereIn('status', ['pending', 'pending_review'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => 1, 'result' => $ads]);
    }


    public function timedAdRequests()
    {
        $ads = Ad::with(['images', 'position', 'adable'])
            ->whereIn('status', ['pending', 'pending_review'])
            ->whereNotNull('expires_at')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => 1, 'result' => $ads]);
    }


    public function bannerdAdRequests()
    {
        $ads = Ad::with(['images', 'position', 'adable'])
            ->whereIn('status', ['pending', 'pending_review'])
            ->whereHas('position', function ($q) {
                $q->where('name', 'banner');
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => 1, 'result' => $ads]);
    }



    // Create a new ad
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'expires_at' => 'nullable|date|after:now',
            'ad_position_id' => 'required|exists:ad_positions,id',
            'adable_type' => 'required|string',
            'adable_id' => 'nullable|integer',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
        ]);

        $type = strtolower((string) $validated['adable_type'] ?? '');
        if (!isset(self::ALLOWED_ADABLES[$type])) {
            return response()->json([
                'success' => 0,
                'message' => __('ads.invalid_adable_type'),
            ], 422);
        }
        $user = $request->user();
        abort_unless($this->isApprovedBusinessUser($user), 403, 'Only approved sellers and approved service providers can create sponsored ads.');

        $validated['adable_type'] = self::ALLOWED_ADABLES[$type];
        $adableId = $validated['adable_id'] ?? null;

        if (empty($validated['adable_id'])) {
            if ($validated['adable_type'] === \App\Models\Seller::class) {
                $seller = $user->seller;
                if (!$seller) {
                    return response()->json([
                        'success' => 0,
                        'message' => __('ads.seller_not_found'),
                    ], 422);
                }
                $adableId = $seller->id;
            }
            if ($validated['adable_type'] === \App\Models\ServiceProvider::class) {
                $serviceProvider = $user->serviceProvider;
                if (!$serviceProvider || $serviceProvider->status !== 'approved') {
                    return response()->json([
                        'success' => 0,
                        'message' => __('ads.service_provider_not_found'),
                    ], 422);
                }
                $adableId = $serviceProvider->id;
            }
        }
        $validated['adable_id'] = $adableId;

        if (!$adableId || !$this->authorizeAdableOwner($user, $validated['adable_type'], $adableId)) {
            return response()->json([
                'success' => 0,
                'message' => __('ads.not_authorized'),
            ], 403);
        }
        $position = AdPosition::query()->findOrFail($validated['ad_position_id']);
        $pricing = $this->getPricingForPosition($position->name);
        abort_if($pricing['price'] === null, 422, 'Unsupported sponsored ad placement.');

        DB::beginTransaction();
        try {
            $ad = Ad::create([
                ...$validated,
                'price' => $pricing['price'],
                'status' => 'pending_payment',
                'metadata' => [
                    'tier' => $pricing['tier'],
                ],
            ]);

            // Attach images
            if ($request->hasFile('images')) {
                $disk = MediaPath::uploadsDisk();
                foreach ($request->file('images') as $idx => $imgFile) {
                    $path = $imgFile->store("ads/{$ad->id}", $disk);
                    $ad->images()->create([
                        'image_url' => $path,
                        'sort_order' => $idx,
                    ]);
                }
            }

            DB::commit();
            return response()->json(['success' => 1, 'result' => $ad->load('images', 'position')]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => 0,
                'message' => __('ads.create_failed'),
                'result' => ['error' => $e->getMessage()],
            ], 500);
        }
    }

    // Show a single ad
    public function show($id)
    {
        $ad = Ad::with(['images', 'position', 'adable'])->findOrFail($id);
        return response()->json(['success' => 1, 'result' => $ad]);
    }

    public function createCheckoutSession(Request $request, Ad $ad, StripeClient $stripe)
    {
        $this->authorizeAdOwner($request->user(), $ad);

        abort_if($ad->status !== 'pending_payment', 422, 'This sponsored ad no longer requires payment.');
        abort_if((float) $ad->price <= 0, 422, 'Invalid sponsored ad price.');

        $metadata = $ad->metadata ?? [];
        $frontendUrl = rtrim((string) config('stripe.frontend_url'), '/');
        $successBaseUrl = config('ads.checkout_success_url') ?: ($frontendUrl . '/account/ads/checkout/success');
        $cancelBaseUrl = config('ads.checkout_cancel_url') ?: ($frontendUrl . '/account/ads/checkout/cancel');
        $successUrl = $successBaseUrl . (str_contains($successBaseUrl, '?') ? '&' : '?') . 'ad_id=' . $ad->id . '&session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $cancelBaseUrl . (str_contains($cancelBaseUrl, '?') ? '&' : '?') . 'ad_id=' . $ad->id;

        $session = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower(config('ads.currency', config('stripe.currency', 'eur'))),
                    'product_data' => [
                        'name' => 'Sponsored ad: ' . $ad->title,
                        'description' => $this->buildCheckoutDescription($ad),
                        'metadata' => [
                            'ad_id' => (string) $ad->id,
                        ],
                    ],
                    'unit_amount' => (int) round(((float) $ad->price) * 100),
                ],
                'quantity' => 1,
            ]],
            'metadata' => [
                'ad_id' => (string) $ad->id,
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ], [
            'idempotency_key' => 'ad_cs_' . $ad->id . '_' . Str::uuid()->toString(),
        ]);

        $metadata['last_checkout_session_id'] = $session->id;
        $metadata['last_checkout_session_created_at'] = now()->toISOString();
        $ad->update(['metadata' => $metadata]);

        return response()->json([
            'checkout_url' => $session->url,
            'checkout_session_id' => $session->id,
            'ad_id' => $ad->id,
            'status' => $ad->status,
        ]);
    }

    public function reconcileCheckoutSession(Request $request, Ad $ad, StripeClient $stripe)
    {
        $this->authorizeAdOwner($request->user(), $ad);

        $data = $request->validate([
            'session_id' => ['required', 'string'],
        ]);

        $session = $stripe->checkout->sessions->retrieve($data['session_id'], []);
        $sessionArray = $session instanceof \Stripe\StripeObject ? $session->toArray() : (array) $session;
        $sessionAdId = $sessionArray['metadata']['ad_id'] ?? null;

        abort_if((string) $sessionAdId !== (string) $ad->id, 422, 'Checkout session does not belong to this sponsored ad.');

        if (($sessionArray['payment_status'] ?? null) === 'paid') {
            $metadata = array_merge($ad->metadata ?? [], [
                'last_paid_session_id' => $sessionArray['id'] ?? null,
                'last_paid_session' => $sessionArray,
            ]);

            $ad->update([
                'status' => 'pending_review',
                'paid_at' => now(),
                'metadata' => $metadata,
            ]);
        }

        return response()->json([
            'ad' => $ad->fresh(['images', 'position', 'adable']),
            'is_paid' => $ad->fresh()->paid_at !== null,
        ]);
    }

    // Update ad status (approve/reject) - admin only
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:approved,rejected',
        ]);

        $ad = Ad::findOrFail($id);
        if ($validated['status'] === 'approved' && $ad->paid_at === null) {
            abort(422, 'Sponsored ads must be paid before approval.');
        }

        if ($validated['status'] === 'rejected' && $ad->paid_at !== null && $ad->refund_status !== 'refunded') {
            app(PromotionRefundService::class)->refundAdRejection($ad);
        }

        $ad->status = $validated['status'];
        $ad->save();

        return response()->json(['success' => 1, 'result' => $ad]);
    }


    // Update ad
    public function update(Request $request, $id)
    {
        $ad = Ad::findOrFail($id);
        $this->authorizeAdOwner($request->user(), $ad);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'price'      => 'nullable|numeric|min:0|max:999999999.99',
            'expires_at' => 'nullable|date',
            'ad_position_id' => 'sometimes|exists:ad_positions,id',
            'status' => 'sometimes|string|in:pending,approved,rejected,expired',
        ]);

        $ad->update($validated);

        return response()->json(['success' => 1, 'result' => $ad->fresh(['images', 'position', 'adable'])]);
    }

    // Delete ad (soft delete if using SoftDeletes)
    public function destroy($id)
    {
        $ad = Ad::findOrFail($id);
        $this->authorizeAdOwner(request()->user(), $ad);
        $ad->delete();

        return response()->json([
            'success' => 1,
            'message' => __('ads.deleted'),
        ]);
    }

    // Attach more images
    public function addImages(Request $request, $id)
    {
        $ad = Ad::findOrFail($id);
        $this->authorizeAdOwner($request->user(), $ad);
        $validated = $request->validate([
            'images' => 'required|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
        ]);
        if ($request->hasFile('images')) {
            $disk = MediaPath::uploadsDisk();
            foreach ($request->file('images') as $idx => $imgFile) {
                $path = $imgFile->store("ads/{$ad->id}", $disk);
                $ad->images()->create([
                    'image_url' => $path,
                    'sort_order' => $idx,
                ]);
            }
        }
        return response()->json(['success' => 1, 'result' => $ad->images]);
    }

    private function authorizeAdOwner($user, Ad $ad): void
    {
        if (!$user) abort(401);

        $adable = $ad->adable;
        if (!$adable) abort(404);

        if (!$this->authorizeAdableOwner($user, $ad->adable_type, $ad->adable_id)) {
            abort(403, __('ads.not_authorized'));
        }
    }

    private function authorizeAdableOwner($user, string $adableType, int $adableId): bool
    {
        if ($adableType === \App\Models\Seller::class) {
            return (int) ($user->seller?->id ?? 0) === $adableId && $user->seller?->status === 'approved';
        }
        if ($adableType === \App\Models\ServiceProvider::class) {
            return (int) ($user->serviceProvider?->id ?? 0) === $adableId && $user->serviceProvider?->status === 'approved';
        }
        if ($adableType === \App\Models\Product::class) {
            return \App\Models\Product::whereKey($adableId)
                ->whereHas('seller', fn($q) => $q->approved()->where('user_id', $user->id))
                ->exists();
        }
        if ($adableType === \App\Models\Service::class) {
            return \App\Models\Service::whereKey($adableId)
                ->whereHas('serviceProvider', fn($q) => $q->approved()->where('user_id', $user->id))
                ->exists();
        }

        return false;
    }

    public function timedAds(Request $request)
    {
        $ads = $this->publicAdsQuery()
            ->whereNotNull('expires_at')
            ->orderBy('expires_at', 'asc')
            ->paginate(20);

        $this->loadAdableRelations($ads->getCollection());

        return response()->json(['success' => 1, 'result' => $ads]);
    }

    public function myAds(Request $request)
    {
        $userId = $request->user()->id;

        $ads = Ad::with(['images', 'position', 'adable'])
            ->where(function ($q) use ($userId) {
                $q->whereHasMorph(
                    'adable',
                    [Product::class],
                    fn($m) => $m->whereHas('seller', fn($s) => $s->where('user_id', $userId))
                )
                ->orWhereHasMorph(
                    'adable',
                    [Service::class],
                    fn($m) => $m->whereHas('serviceProvider', fn($s) => $s->where('user_id', $userId))
                )
                ->orWhereHasMorph(
                    'adable',
                    [Seller::class],
                    fn($m) => $m->where('user_id', $userId)
                )
                ->orWhereHasMorph(
                    'adable',
                    [ServiceProvider::class],
                    fn($m) => $m->where('user_id', $userId)
                );
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => 1, 'result' => $ads]);
    }

    private function publicAdsQuery()
    {
        return Ad::query()
            ->where('status', 'approved')
            ->where('adable_type', '!=', Listing::class)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function ($query) {
                $query
                    ->whereHasMorph(
                        'adable',
                        [Product::class],
                        fn($m) => $m->whereHas('seller', fn($seller) => $seller->approved()->withActiveUser()),
                    )
                    ->orWhereHasMorph(
                        'adable',
                        [Service::class],
                        fn($m) => $m->whereHas('serviceProvider', fn($provider) => $provider->approved()->withActiveUser()),
                    )
                    ->orWhereHasMorph(
                        'adable',
                        [Seller::class],
                        fn($m) => $m->approved()->withActiveUser(),
                    )
                    ->orWhereHasMorph(
                        'adable',
                        [ServiceProvider::class],
                        fn($m) => $m->approved()->withActiveUser(),
                    );
            })
            ->with(['images', 'position', 'adable'])
            ->orderByDesc('created_at');
    }

    private function loadAdableRelations($ads): void
    {
        $ads->each(function ($ad) {
            $adable = $ad->adable;
            if (!$adable) {
                return;
            }

            $class = get_class($adable);
            if ($class === Product::class) {
                $adable->loadMissing('seller.user');
            } elseif ($class === Service::class) {
                $adable->loadMissing('serviceProvider.user');
            } elseif (in_array($class, [Seller::class, ServiceProvider::class], true)) {
                $adable->loadMissing('user');
            }
        });
    }

    private function isApprovedBusinessUser($user): bool
    {
        return $user->seller?->status === 'approved' || $user->serviceProvider?->status === 'approved';
    }

    private function getPricingForPosition(string $positionName): array
    {
        return config('ads.tiers.' . $positionName, [
            'tier' => null,
            'price' => null,
        ]);
    }

    private function buildCheckoutDescription(Ad $ad): string
    {
        $tier = $ad->metadata['tier'] ?? null;
        $parts = array_filter([
            $tier ? 'Tier: ' . ucfirst((string) $tier) : null,
            $ad->subtitle,
        ]);

        return implode(' | ', $parts);
    }
}
