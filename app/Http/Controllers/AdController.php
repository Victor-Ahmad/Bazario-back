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


class AdController extends Controller
{

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
            })
            ->with(['images', 'position', 'adable'])
            ->orderByDesc('created_at')
            ->paginate(20);

        $ads->getCollection()->each(function ($ad) {
            $adable = $ad->adable;
            if (!$adable) return;

            $class = get_class($adable);
            if (in_array($class, [Product::class, Service::class])) {
                $adable->loadMissing('seller.user');
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
            'adable_type' => 'nullable|string',
            'adable_id' => 'nullable|integer',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg,webp',
        ]);

        // Optionally check adable_type is allowed, e.g.:
        // $allowedTypes = [
        //     \App\Models\Product::class,
        //     \App\Models\Service::class,
        //     \App\Models\Seller::class,
        //     \App\Models\ServiceProvider::class,
        // ];
        $map = [
            'product' => \App\Models\Product::class,
            'service' => \App\Models\Service::class,
            'seller'  => \App\Models\Seller::class,
            'service_provider'  => \App\Models\ServiceProvider::class,
            'listing'  => \App\Models\Listing::class,
        ];
        $type = strtolower((string) $validated['adable_type'] ?? '');
        if (!isset($map[$type])) {
            return response()->json(['success' => 0, 'message' => 'Invalid adable_type'], 422);
        }
        $validated['adable_type'] = $map[$type];
        if (empty($validated['adable_id'])) {
            if ($validated['adable_type'] === \App\Models\Seller::class) {
                $seller = auth()->guard()->user()->seller;
                if (!$seller) {
                    return response()->json(['success' => 0, 'message' => 'Seller not found'], 422);
                }
                $validated['adable_id'] = $seller->id;
            }
            if ($validated['adable_type'] === \App\Models\ServiceProvider::class) {
                $service_provider = auth()->guard()->user()->service_provider;
                if (!$service_provider) {
                    return response()->json(['success' => 0, 'message' => 'Service Provider not found'], 422);
                }
                $validated['adable_id'] = $service_provider->id;
            }
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
            return response()->json(['success' => 0, 'message' => $e->getMessage()], 500);
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
        $ad->delete();

        return response()->json(['success' => 1, 'message' => 'Ad deleted']);
    }

    // Attach more images
    public function addImages(Request $request, $id)
    {
        $ad = Ad::findOrFail($id);
        $validated = $request->validate([
            'images' => 'required|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg,webp',
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

    public function timedAds(Request $request)
    {
        $ads = Ad::with(['images', 'position', 'adable'])
            ->whereNotNull('expires_at')
            // ->where('status', 'approved')
            ->orderBy('expires_at', 'asc')
            ->paginate(20);

        return response()->json(['success' => 1, 'result' => $ads]);
    }
}
