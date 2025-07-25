<?php

namespace App\Http\Controllers;

use App\Http\Requests\Ads\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Traits\ApiResponseTrait;


class ProductController extends Controller
{
    use ApiResponseTrait;
    public function index()
    {
        $query = Product::with([
            'images:id,product_id,image',
            'category:id,name',
            'seller.user:id,name,email,phone',
            'seller:id,user_id,store_name,store_owner_name,logo,address,description'
        ])
            ->select('id', 'name', 'description',  'price',  'category_id', 'seller_id', 'created_at');

        if (request()->has('category_id')) {
            $query->where('category_id', request('category_id'));
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(20);

        return $this->successResponse($products, 'messages', 'products_retrieved_successfully');
    }

    public function myProducts()
    {
        $user = auth()->user();
        $seller = Seller::where('user_id', $user->id)->first();

        $query = Product::where('seller_id', $seller->id)
            ->with([
                'images:id,product_id,image',
                'category:id,name',
                'seller.user:id,name,email,phone',
                'seller:id,user_id,store_name,store_owner_name,logo,address,description'
            ])
            ->select('id', 'name', 'description',  'price',  'category_id', 'seller_id', 'created_at');

        if (request()->has('category_id')) {
            $query->where('category_id', request('category_id'));
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(20);

        return $this->successResponse($products, 'messages', 'products_retrieved_successfully');
    }

    public function productsBySeller($id)
    {
        $products = Product::where('seller_id', $id)->with([
            'images:id,product_id,image',
            'category:id,name',
            'seller.user:id,name,email,phone',
            'seller:id,user_id,store_name,store_owner_name,logo,address,description'
        ])
            ->select('id', 'name', 'description',  'price',  'category_id', 'seller_id', 'created_at')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->successResponse($products, 'messages', 'products_retrieved_successfully');
    }

    public function store(ProductRequest $request)
    {
        $data = $request->except('images');
        $user = auth()->user();
        $seller = Seller::where('user_id', $user->id)->first();

        $data['seller_id'] = $seller->id;
        $product = Product::create($data);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                $product->images()->create([
                    'image' => 'storage/' . $image->store('products/' . $seller->id, 'public'),
                ]);
            }
        }

        return $this->successResponse($product->load('images'), 'products', 'product_created_successfully');
    }


    public function productsByCategory($categoryId)
    {
        $categoryIds = Category::where('parent_id', $categoryId)
            ->pluck('id')->toArray();
        $categoryIds[] = $categoryId;

        $products = Product::whereIn('category_id', $categoryIds)
            ->with([
                'images:id,product_id,image',
                'category:id,name',
                'seller.user:id,name,email,phone',
                'seller:id,user_id,store_name,store_owner_name,logo,address,description'
            ])
            ->select('id', 'name', 'description', 'price', 'category_id', 'seller_id', 'created_at')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->successResponse($products, 'messages', 'products_retrieved_successfully');
    }
}
