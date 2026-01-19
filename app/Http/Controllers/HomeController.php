<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\Product;
use App\Models\Service;
use App\Traits\ApiResponseTrait;

class HomeController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {
        $perPage = (int) request('per_page', 10);
        $perPage = max(1, min($perPage, 50)); // safety

        // Products (10)
        $products = Product::with([
            'images:id,product_id,image',
            'category:id,name',
            'seller.user:id,name,email,phone',
            'seller:id,user_id,store_name,store_owner_name,logo,address,description'
        ])
            ->select('id', 'name', 'description', 'price', 'category_id', 'seller_id', 'created_at')
            ->orderByDesc('created_at')
            ->paginate(
                $perPage,
                ['*'],
                'products_page',
                (int) request('products_page', 1)
            );

        // Services (10)
        $services = Service::with([
            'images:id,service_id,image',
            'category:id,name',
            'serviceProvider.user:id,name,email,phone',
            'serviceProvider:id,user_id,name,logo,address,description'
        ])
            ->select('id', 'title', 'description', 'price', 'category_id', 'provider_id', 'created_at')
            ->orderByDesc('created_at')
            ->paginate(
                $perPage,
                ['*'],
                'services_page',
                (int) request('services_page', 1)
            );

        // Ads (10) – approved only, newest first
        $ads = Ad::with(['images', 'position', 'adable'])
            ->where('status', 'approved')
            ->orderByDesc('created_at')
            ->paginate(
                $perPage,
                ['*'],
                'ads_page',
                (int) request('ads_page', 1)
            );

        return $this->successResponse([
            'products' => $products,
            'services' => $services,
            'ads'      => $ads,
        ], 'messages', 'home_retrieved_successfully');
    }
}
