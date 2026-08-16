<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class PlatformSettingsController extends Controller
{
    private function defaults(): array
    {
        return [
            'platform_fee_percent' => 10.0,
            'announcement_price_per_day' => (float) config('listings.announcement.price_per_day', 20),
            'ad_price_per_day_golden_ad' => (float) config('ads.tiers.golden_ad.price_per_day', 50),
            'ad_price_per_day_silver_ad' => (float) config('ads.tiers.silver_ad.price_per_day', 40),
            'ad_price_per_day_normal_ad' => (float) config('ads.tiers.normal_ad.price_per_day', 30),
        ];
    }

    public function show()
    {
        $defaults = $this->defaults();

        return response()->json(collect($defaults)
            ->mapWithKeys(fn ($default, $key) => [$key => (float) Setting::getValue($key, $default)])
            ->all());
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'platform_fee_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'announcement_price_per_day' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'ad_price_per_day_golden_ad' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'ad_price_per_day_silver_ad' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'ad_price_per_day_normal_ad' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        foreach ($data as $key => $value) {
            Setting::setValue($key, (float) $value);
        }

        return response()->json([
            'message' => 'Platform settings updated.',
            ...collect($data)
                ->mapWithKeys(fn ($value, $key) => [$key => (float) $value])
                ->all(),
        ]);
    }
}
