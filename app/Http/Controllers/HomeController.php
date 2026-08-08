<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\Listing;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Traits\ApiResponseTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    use ApiResponseTrait;

    private const LATEST_LIMIT = 8;
    private const GOLD_LIMIT = 1;
    private const SILVER_LIMIT = 3;
    private const NORMAL_LIMIT = 4;
    private const ANNOUNCEMENT_LIMIT = 3;

    public function index(Request $request)
    {
        $latestLimit = max(1, min((int) $request->integer('latest_limit', self::LATEST_LIMIT), 24));

        return $this->successResponse([
            'products' => [
                'latest' => $this->latestProducts($latestLimit),
            ],
            'services' => [
                'latest' => $this->latestServices($latestLimit),
            ],
            'ads' => [
                'gold' => $this->adsByPosition('golden_ad', self::GOLD_LIMIT),
                'silver' => $this->adsByPosition('silver_ad', self::SILVER_LIMIT),
                'normal' => $this->adsByPosition('normal_ad', self::NORMAL_LIMIT),
                'announcements' => $this->announcementAds(self::ANNOUNCEMENT_LIMIT),
            ],
        ], 'messages', 'home_retrieved_successfully');
    }

    private function latestProducts(int $limit)
    {
        return $this->productQuery()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    private function latestServices(int $limit)
    {
        return $this->serviceQuery()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    private function productQuery(): Builder
    {
        return Product::query()
            ->whereHas('seller', function ($query) {
                $query->approved()->withActiveUser();
            })
            ->with([
                'images:id,product_id,image',
                'category:id,name',
                'seller.user:id,name,email,phone',
                'seller:id,user_id,store_name,store_owner_name,logo,address,description',
            ])
            ->select('id', 'name', 'description', 'price', 'category_id', 'seller_id', 'created_at');
    }

    private function serviceQuery(): Builder
    {
        return Service::query()
            ->whereHas('serviceProvider', function ($query) {
                $query->approved()->withActiveUser();
            })
            ->with([
                'images:id,service_id,image',
                'category:id,name',
                'serviceProvider.user:id,name,email,phone',
                'serviceProvider:id,user_id,name,logo,address,description',
            ])
            ->select('id', 'title', 'description', 'price', 'category_id', 'provider_id', 'created_at');
    }

    private function publicAdsQuery(): Builder
    {
        return Ad::query()
            ->where('status', 'approved')
            ->where(function (Builder $query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->with(['images', 'position', 'adable'])
            ->orderByDesc('created_at');
    }

    private function adsByPosition(string $positionName, int $limit)
    {
        $ads = $this->publicAdsQuery()
            ->whereHas('position', function (Builder $query) use ($positionName) {
                $query->whereRaw('LOWER(name) = ?', [mb_strtolower($positionName)]);
            })
            ->limit($limit)
            ->get();

        return $this->hydrateAdableRelations($ads);
    }

    private function announcementAds(int $limit)
    {
        $ads = $this->publicAdsQuery()
            ->where('adable_type', Listing::class)
            ->limit($limit)
            ->get();

        return $this->hydrateAdableRelations($ads);
    }

    private function hydrateAdableRelations($ads)
    {
        $ads->each(function (Ad $ad) {
            $adable = $ad->adable;

            if (!$adable) {
                return;
            }

            $class = get_class($adable);

            if ($class === Product::class) {
                $adable->loadMissing('seller.user');
                return;
            }

            if ($class === Service::class) {
                $adable->loadMissing('serviceProvider.user');
                return;
            }

            if (in_array($class, [Seller::class, ServiceProvider::class], true)) {
                $adable->loadMissing('user');
                return;
            }

            if ($class === Listing::class) {
                $adable->loadMissing(['user', 'images', 'coverImage']);
            }
        });

        return $ads;
    }
}
