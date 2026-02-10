<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class PlatformSettingsController extends Controller
{
    public function show()
    {
        return response()->json([
            'platform_fee_percent' => (float) Setting::getValue('platform_fee_percent', 10),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'platform_fee_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        Setting::setValue('platform_fee_percent', (float) $data['platform_fee_percent']);

        return response()->json([
            'message' => 'Platform settings updated.',
            'platform_fee_percent' => (float) $data['platform_fee_percent'],
        ]);
    }
}
