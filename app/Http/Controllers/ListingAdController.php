<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListingWithAdRequest;
use App\Models\Ad;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ListingAdController extends Controller
{
    public function store(ListingWithAdRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $disk = 'public';

        return DB::transaction(function () use ($user, $data, $request, $disk) {
            $listing = Listing::create([
                'user_id'     => $user->id,
                'title'       => $data['title'],
                'description' => $data['description'] ?? null,
                'price'       => $data['price'] ?? null,
                'attributes'  => $data['attributes'] ?? null,
            ]);

            $coverIndex = (int)($data['cover_index'] ?? 0);
            $storedRelPaths = [];

            foreach ($request->file('images', []) as $i => $file) {
                $relPath = $file->store("listings/{$listing->id}", $disk);
                $storedRelPaths[] = $relPath;

                $listing->images()->create([
                    'path'     => $relPath,
                    'sort'     => $i,
                    'is_cover' => ($i === $coverIndex),
                ]);
            }

            if ($listing->images()->exists() && !$listing->coverImage()->exists()) {
                $first = $listing->images()->orderBy('sort')->orderBy('id')->first();
                $first?->update(['is_cover' => true]);
            }

            $expiresAt = $data['ad']['expires_at'] ?? null;

            $ad = Ad::create([
                'title'          => $data['ad']['title'],
                'subtitle'       => $data['ad']['subtitle'] ?? null,
                'price'          => $data['price'] ?? null,
                'ad_position_id' => $data['ad']['ad_position_id'] ?? null,
                'expires_at'     => $expiresAt,
                'status'         => 'pending',
                'adable_type'    => \App\Models\Listing::class,
                'adable_id'      => $listing->id,
            ]);


            $adCreatives = $request->file('ad.images', []);
            if (!empty($adCreatives)) {
                foreach ($adCreatives as $idx => $creative) {
                    $relPath = $creative->store("ads/{$ad->id}", $disk);
                    $ad->images()->create([
                        'image_url'  => 'storage/' . $relPath,
                        'sort_order' => $idx,
                    ]);
                }
            } else {
                foreach ($storedRelPaths as $idx => $relPath) {
                    $ad->images()->create([
                        'image_url'  => 'storage/' . $relPath,
                        'sort_order' => $idx,
                    ]);
                }
            }

            return response()->json([
                'success' => 1,
                'result'  => [
                    'listing' => $listing->load(['images', 'coverImage']),
                    'ad'      => $ad->load(['images', 'position', 'adable']),
                ],
            ], 201);
        });
    }
}
