<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Support\MediaPath;
use App\Services\PromotionRefundService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Stripe\StripeClient;

class ListingController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request)
    {
        $listings = Listing::query()
            ->approved()
            ->whereHas('user')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
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

        $isPubliclyVisible = $listing->status === 'approved'
            && $listing->user()->exists()
            && ($listing->expires_at === null || $listing->expires_at->isFuture());

        abort_unless($isPubliclyVisible || $isOwner || $isAdmin, 404);
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
            'images' => ['required', 'array', 'max:12'],
            'images.*' => ['file', 'image', 'max:4096'],
        ]);

        $uploadsDisk = config('bazario.uploads_disk', 'public');

        $listing = DB::transaction(function () use ($request, $data, $uploadsDisk) {
            $durationDays = (int) config('listings.announcement.duration_days', 1);
            $pricePerDay = (float) config('listings.announcement.price_per_day', 20);

            $listing = Listing::create([
                'user_id' => $request->user()->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'price' => round($pricePerDay * $durationDays, 2),
                'duration_days' => $durationDays,
                'status' => 'pending_payment',
            ]);

            foreach ($request->file('images', []) as $index => $file) {
                $relPath = $file->store("listings/{$listing->id}", $uploadsDisk);

                $listing->images()->create([
                    'path' => $relPath,
                    'sort' => $index,
                    'is_cover' => $index === 0,
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
            'message' => 'Announcement created. Payment is required before review.',
        ], 201);
    }

    public function pending(Request $request)
    {
        $listings = Listing::query()
            ->where('status', 'pending_review')
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

        if ($data['status'] === 'approved' && $listing->paid_at === null) {
            abort(422, 'Announcements must be paid before approval.');
        }

        if ($data['status'] === 'rejected' && $listing->paid_at !== null && $listing->refund_status !== 'refunded') {
            app(PromotionRefundService::class)->refundListingRejection($listing);
        }

        $listing->update([
            'status' => $data['status'],
        ]);

        return $this->successResponse($listing->fresh(), 'messages', 'updated_successfully');
    }

    public function pricing()
    {
        return response()->json([
            'success' => 1,
            'result' => [
                'price_per_day' => (float) config('listings.announcement.price_per_day', 20),
                'duration_days' => (int) config('listings.announcement.duration_days', 1),
                'total_price' => round(
                    (float) config('listings.announcement.price_per_day', 20)
                    * (int) config('listings.announcement.duration_days', 1),
                    2,
                ),
                'currency_iso' => strtoupper((string) config('listings.currency', config('stripe.currency', 'eur'))),
            ],
        ]);
    }

    public function createCheckoutSession(Request $request, Listing $listing, StripeClient $stripe)
    {
        $user = $request->user();
        $this->authorizeListingOwner($user, $listing);

        abort_if($listing->status !== 'pending_payment', 422, 'This announcement no longer requires payment.');
        abort_if((float) $listing->price <= 0, 422, 'Invalid announcement price.');

        $metadata = $listing->metadata ?? [];
        $frontendUrl = rtrim((string) config('stripe.frontend_url'), '/');
        $successBaseUrl = config('listings.checkout_success_url') ?: ($frontendUrl . '/account/announcements/checkout/success');
        $cancelBaseUrl = config('listings.checkout_cancel_url') ?: ($frontendUrl . '/account/announcements/checkout/cancel');
        $successUrl = $successBaseUrl . (str_contains($successBaseUrl, '?') ? '&' : '?') . 'listing_id=' . $listing->id . '&session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $cancelBaseUrl . (str_contains($cancelBaseUrl, '?') ? '&' : '?') . 'listing_id=' . $listing->id;
        $checkoutCustomer = $this->resolveCheckoutCustomer($stripe, $user);

        $session = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower((string) config('listings.currency', config('stripe.currency', 'eur'))),
                    'product_data' => [
                        'name' => 'Announcement: ' . $listing->title,
                        'description' => $listing->description ?: 'Marketplace announcement submission',
                        'metadata' => [
                            'listing_id' => (string) $listing->id,
                        ],
                    ],
                    'unit_amount' => (int) round(((float) $listing->price) * 100),
                ],
                'quantity' => 1,
            ]],
            'metadata' => [
                'listing_id' => (string) $listing->id,
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            ...$checkoutCustomer,
        ], [
            'idempotency_key' => 'listing_cs_' . $listing->id . '_' . Str::uuid()->toString(),
        ]);

        $metadata['last_checkout_session_id'] = $session->id;
        $metadata['last_checkout_session_created_at'] = now()->toISOString();
        $listing->update(['metadata' => $metadata]);

        return response()->json([
            'checkout_url' => $session->url,
            'checkout_session_id' => $session->id,
            'listing_id' => $listing->id,
            'status' => $listing->status,
        ]);
    }

    public function reconcileCheckoutSession(Request $request, Listing $listing, StripeClient $stripe)
    {
        $this->authorizeListingOwner($request->user(), $listing);

        $data = $request->validate([
            'session_id' => ['required', 'string'],
        ]);

        $session = $stripe->checkout->sessions->retrieve($data['session_id'], []);
        $sessionArray = $session instanceof \Stripe\StripeObject ? $session->toArray() : (array) $session;
        $sessionListingId = $sessionArray['metadata']['listing_id'] ?? null;

        abort_if((string) $sessionListingId !== (string) $listing->id, 422, 'Checkout session does not belong to this announcement.');

        if (($sessionArray['payment_status'] ?? null) === 'paid') {
            $metadata = array_merge($listing->metadata ?? [], [
                'checkout_session_id' => $sessionArray['id'] ?? null,
                'payment_intent_id' => $sessionArray['payment_intent'] ?? null,
                'last_paid_session' => $sessionArray,
            ]);

            $paidAt = now();

            $listing->update([
                'status' => 'pending_review',
                'paid_at' => $paidAt,
                'expires_at' => $paidAt->copy()->addDays(max(1, (int) $listing->duration_days)),
                'metadata' => $metadata,
            ]);
        }

        return response()->json([
            'listing' => $listing->fresh([
                'user:id,name',
                'images:id,listing_id,path,sort,is_cover',
                'coverImage:id,listing_id,path,sort,is_cover',
            ]),
            'is_paid' => $listing->fresh()->paid_at !== null,
        ]);
    }

    public function destroy(Request $request, Listing $listing)
    {
        $this->authorizeListingOwner($request->user(), $listing);

        abort_if(
            $listing->status !== 'pending_payment' || $listing->paid_at !== null,
            422,
            'Only unpaid announcements can be deleted.',
        );

        $disk = MediaPath::uploadsDisk();

        DB::transaction(function () use ($listing, $disk) {
            foreach ($listing->images as $image) {
                $storedPath = MediaPath::normalizeStoredPath((string) $image->getRawOriginal('path'));

                if ($storedPath !== '') {
                    Storage::disk($disk)->delete($storedPath);
                }

                $image->delete();
            }

            $listing->delete();
        });

        return response()->json([
            'success' => 1,
            'message' => 'Announcement deleted successfully.',
        ]);
    }

    private function authorizeListingOwner($user, Listing $listing): void
    {
        abort_unless($user, 401);
        abort_if((int) $listing->user_id !== (int) $user->id, 403);
    }

    private function resolveCheckoutCustomer(StripeClient $stripe, $user): array
    {
        $email = trim((string) ($user?->email ?? ''));

        if ($email === '') {
            return [];
        }

        $customer = $stripe->customers->all([
            'email' => $email,
            'limit' => 1,
        ])->data[0] ?? null;

        if (!$customer) {
            $customer = $stripe->customers->create([
                'email' => $email,
                'name' => $user?->name ?: null,
                'metadata' => [
                    'user_id' => (string) ($user?->id ?? ''),
                ],
            ]);
        }

        return [
            'customer' => $customer->id,
            'payment_intent_data' => [
                'receipt_email' => $email,
            ],
        ];
    }
}
