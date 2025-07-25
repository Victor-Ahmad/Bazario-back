<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\AdImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdController extends Controller
{
    // List all ads (optionally filter by status, type, etc)
    public function index(Request $request)
    {
        $ads = Ad::with(['images', 'position', 'adable'])
            // ->where('status', 'approved')
            ->whereNull('expires_at')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => 1, 'result' => $ads]);
    }
    public function getPendingAds(Request $request)
    {
        $ads = Ad::with(['images', 'position', 'adable'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => 1, 'result' => $ads]);
    }


    public function timedAdRequests(Request $request)
    {
        $ads = Ad::with(['images', 'position', 'adable'])
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => 1, 'result' => $ads]);
    }


    public function bannerdAdRequests(Request $request)
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
            'expires_at' => 'nullable|date',
            'ad_position_id' => 'required|exists:ad_positions,id',
            'adable_type' => 'required|string',
            'adable_id' => 'nullable|integer',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg,webp',
        ]);

        // Optionally check adable_type is allowed, e.g.:
        $allowedTypes = [
            \App\Models\Product::class,
            \App\Models\Service::class,
            \App\Models\Seller::class,
            \App\Models\Talent::class,
        ];
        if (!in_array($validated['adable_type'], $allowedTypes)) {
            return response()->json(['success' => 0, 'message' => 'Invalid adable_type'], 422);
        }
        if (empty($validated['adable_id'])) {
            if ($validated['adable_type'] === \App\Models\Seller::class) {
                $seller = auth()->user()->seller;
                if (!$seller) {
                    return response()->json(['success' => 0, 'message' => 'Seller not found'], 422);
                }
                $validated['adable_id'] = $seller->id;
            }
            if ($validated['adable_type'] === \App\Models\Talent::class) {
                $talent = auth()->user()->talent;
                if (!$talent) {
                    return response()->json(['success' => 0, 'message' => 'Talent not found'], 422);
                }
                $validated['adable_id'] = $talent->id;
            }
        }
        DB::beginTransaction();
        try {
            $ad = Ad::create([
                ...$validated,
                'status' => 'pending',
            ]);

            // Attach images
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $idx => $imgFile) {
                    $path = $imgFile->store('ad_uploads/images', 'public');
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
                $path = $imgFile->store('ads/images', 'public');
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
