<?php

namespace App\Http\Controllers;

use App\Http\Requests\Ads\AdRequest;
use App\Models\Ad;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class AdsController extends Controller
{
    use ApiResponseTrait;
    public function index()
    {

        $ads = Ad::with('images')->get();
        return $this->successResponse($ads, 'messages', 'adsـretrievedـsuccessfully');
    }

    public function store(AdRequest $request)
    {
        $data = $request->except('images');
        $user = auth()->user();
        if ($request->hasFile('image')) {
            $data['image'] = 'storage/' . $request->file('image')->store('ads', 'public');
        }
        $data['added_by'] = $user->id;
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
