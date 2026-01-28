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


class AdController extends Controller
{
    private const ALLOWED_ADABLES = [
        'product' => \App\Models\Product::class,
        'service' => \App\Models\Service::class,
        'seller'  => \App\Models\Seller::class,
        'service_provider'  => \App\Models\ServiceProvider::class,
        'listing'  => \App\Models\Listing::class,
    ];

    public function index()
    {
        $ads = Ad::with(['images', 'position', 'adable'])
            ->where('status', 'approved')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

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
        $ads = Ad::query()
            ->where('status', 'approved')
            ->whereHas('position', function ($q) use ($name) {
                $q->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);
                // whereRaw("LOWER(name) = '$value'")
            })
            ->with(['images', 'position', 'adable'])
            ->orderByDesc('created_at')
            ->paginate(20);

        $ads->getCollection()->each(function ($ad) {
            $adable = $ad->adable;
            if (!$adable) return;

            $class = get_class($adable);
            if ($class == Product::class) {
                $adable->loadMissing('seller.user');
            } elseif ($class == Service::class) {
                $adable->loadMissing('serviceProvider.user');
            } elseif (in_array($class, [Seller::class, ServiceProvider::class])) {
                $adable->loadMissing('user');
            }
        });

        return response()->json(['success' => 1, 'result' => $ads]);
    }
    public function getGeneralAds()
    {
        $ads = Ad::with(['images', 'position', 'adable'])
            ->where('status', 'approved')
            ->where('adable_type', 'App\Models\Listing')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => 1, 'result' => $ads]);
    }
    public function getPendingAds()
    {
        $ads = Ad::with(['images', 'position', 'adable'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => 1, 'result' => $ads]);
    }


    public function timedAdRequests()
    {
        $ads = Ad::with(['images', 'position', 'adable'])
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => 1, 'result' => $ads]);
    }


    public function bannerdAdRequests()
    {
        $ads = Ad::with(['images', 'position', 'adable'])
            ->where('status', 'pending')
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
            'price'      => 'nullable|numeric|min:0|max:999999999.99',
            'expires_at' => 'nullable|date',
            'ad_position_id' => 'nullable|exists:ad_positions,id',
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
        $validated['adable_type'] = self::ALLOWED_ADABLES[$type];

        $user = $request->user();
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
                $service_provider = $user->service_provider;
                if (!$service_provider) {
                    return response()->json([
                        'success' => 0,
                        'message' => __('ads.service_provider_not_found'),
                    ], 422);
                }
                $adableId = $service_provider->id;
            }
        }
        $validated['adable_id'] = $adableId;

        if (!$adableId || !$this->authorizeAdableOwner($user, $validated['adable_type'], $adableId)) {
            return response()->json([
                'success' => 0,
                'message' => __('ads.not_authorized'),
            ], 403);
        }
        DB::beginTransaction();
        try {
            $ad = Ad::create([
                ...$validated,
                'status' => 'pending',
                //  'ad_position_id' => $validated['ad_position_id'] ?? null,
            ]);

            // Attach images
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $idx => $imgFile) {
                    $path = $imgFile->store("ads/{$ad->id}", 'public');
                    $ad->images()->create([
                        'image_url' => 'storage/' . $path,
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

    // Update ad status (approve/reject) - admin only
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:approved,rejected',
        ]);

        $ad = Ad::findOrFail($id);
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
            foreach ($request->file('images') as $idx => $imgFile) {
                $path = $imgFile->store("ads/{$ad->id}", 'public');
                $ad->images()->create([
                    'image_url' => 'storage/' . $path,
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
            return (int) ($user->seller?->id ?? 0) === $adableId;
        }
        if ($adableType === \App\Models\ServiceProvider::class) {
            return (int) ($user->service_provider?->id ?? 0) === $adableId;
        }
        if ($adableType === \App\Models\Product::class) {
            return \App\Models\Product::whereKey($adableId)
                ->whereHas('seller', fn($q) => $q->where('user_id', $user->id))
                ->exists();
        }
        if ($adableType === \App\Models\Service::class) {
            return \App\Models\Service::whereKey($adableId)
                ->whereHas('serviceProvider', fn($q) => $q->where('user_id', $user->id))
                ->exists();
        }
        if ($adableType === \App\Models\Listing::class) {
            return \App\Models\Listing::whereKey($adableId)
                ->where('user_id', $user->id)
                ->exists();
        }

        return false;
    }

    public function timedAds(Request $request)
    {
        $ads = Ad::with(['images', 'position', 'adable'])
            ->whereNotNull('expires_at')
            // ->where('status', 'approved')
            ->orderBy('expires_at', 'asc')
            ->paginate(20);

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
                )
                ->orWhereHasMorph(
                    'adable',
                    [Listing::class],
                    fn($m) => $m->where('user_id', $userId)
                );
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => 1, 'result' => $ads]);
    }
}
