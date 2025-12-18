<?php

namespace App\Http\Controllers;

use App\Http\Requests\Ads\AdRequest;
use App\Models\Ad;
use App\Models\Seller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class AdsController extends Controller
{
    use ApiResponseTrait;
    public function index()
    {
        $ads = Ad::with([
            'images:id,ad_id,image',
            'category:id,name',
            'seller.user:id,name,email,phone',
            'seller:id,user_id,store_name,store_owner_name,logo,address,description'
        ])
            ->select('id', 'title', 'description', 'phone', 'email', 'price', 'quantity', 'category_id', 'added_by')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->successResponse($ads, 'messages', 'ads_retrieved_successfully');
    }

    public function store(AdRequest $request)
    {
        $data = $request->except('images');
        $user = auth()->guard()->user();
        $seller = Seller::where('user_id', $user->id)->first();
        if ($request->hasFile('image')) {
            $data['image'] = 'storage/' . $request->file('image')->store('ads', 'public');
        }
        $data['added_by'] = $seller->id;
        $ad = Ad::create($data);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                if ($index >= 5) break;

                $ad->images()->create([
                    'image' => 'storage/' . $image->store('ads', 'public'),
                ]);
            }
        }

        return $this->successResponse($ad->load('images'), 'ads', 'ad_created_successfully');
    }
}
