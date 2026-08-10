<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ListingController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request)
    {
        $listings = Listing::query()
            ->approved()
            ->whereHas('user')
            ->with([
                'user:id,name',
                'images:id,listing_id,path,sort,is_cover',
                'coverImage:id,listing_id,path,sort,is_cover',
            ])
            ->orderByDesc('created_at')
            ->paginate((int) $request->integer('per_page', 12));

        return $this->successResponse($listings, 'messages', 'fetched_successfully');
    }

    public function show(Request $request, Listing $listing)
    {
        $user = $request->user();
        $isOwner = $user && (int) $listing->user_id === (int) $user->id;
        $isAdmin = $user && $user->hasRole('admin');

        abort_unless($listing->status === 'approved' || $isOwner || $isAdmin, 404);
        abort_unless($listing->user()->exists(), 404);

        $listing->load([
            'user:id,name',
            'images:id,listing_id,path,sort,is_cover',
            'coverImage:id,listing_id,path,sort,is_cover',
        ]);

        return $this->successResponse($listing, 'messages', 'fetched_successfully');
    }

    public function myListings(Request $request)
    {
        $listings = Listing::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'images:id,listing_id,path,sort,is_cover',
                'coverImage:id,listing_id,path,sort,is_cover',
            ])
            ->orderByDesc('created_at')
            ->paginate((int) $request->integer('per_page', 12));

        return $this->successResponse($listings, 'messages', 'fetched_successfully');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'attributes' => ['nullable', 'array'],
            'images' => ['required', 'array', 'max:12'],
            'images.*' => ['file', 'image', 'max:4096'],
            'cover_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $listing = DB::transaction(function () use ($request, $data) {
            $listing = Listing::create([
                'user_id' => $request->user()->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'price' => $data['price'] ?? null,
                'attributes' => $data['attributes'] ?? null,
                'status' => 'pending',
            ]);

            $coverIndex = (int) ($data['cover_index'] ?? 0);

            foreach ($request->file('images', []) as $index => $file) {
                $relPath = $file->store("listings/{$listing->id}", 'public');

                $listing->images()->create([
                    'path' => $relPath,
                    'sort' => $index,
                    'is_cover' => $index === $coverIndex,
                ]);
            }

            if ($listing->images()->exists() && !$listing->coverImage()->exists()) {
                $firstImage = $listing->images()->orderBy('sort')->orderBy('id')->first();
                $firstImage?->update(['is_cover' => true]);
            }

            return $listing;
        });

        $listing->load([
            'images:id,listing_id,path,sort,is_cover',
            'coverImage:id,listing_id,path,sort,is_cover',
        ]);

        return response()->json([
            'success' => 1,
            'result' => $listing,
            'message' => 'Listing submitted for review.',
        ], 201);
    }

    public function pending(Request $request)
    {
        $listings = Listing::query()
            ->pending()
            ->whereHas('user')
            ->with([
                'user:id,name,email',
                'images:id,listing_id,path,sort,is_cover',
                'coverImage:id,listing_id,path,sort,is_cover',
            ])
            ->orderByDesc('created_at')
            ->paginate((int) $request->integer('per_page', 20));

        return $this->successResponse($listings, 'messages', 'fetched_successfully');
    }

    public function updateStatus(Request $request, Listing $listing)
    {
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
        ]);

        $listing->update([
            'status' => $data['status'],
        ]);

        return $this->successResponse($listing->fresh(), 'messages', 'updated_successfully');
    }
}
