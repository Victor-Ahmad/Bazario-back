<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ServiceProvider;
use App\Models\ServiceProviderWorkingHour;
use App\Models\ServiceProviderTimeOff;
use Carbon\Carbon;

class ServiceProviderAvailabilitySeeder extends Seeder
{
    public function run(): void
    {
        $providers = ServiceProvider::with('user')->get();

        foreach ($providers as $provider) {
            ServiceProviderWorkingHour::where('service_provider_id', $provider->id)->delete();
            ServiceProviderTimeOff::where('service_provider_id', $provider->id)->delete();

            $days = [1, 2, 3, 4, 5];
            foreach ($days as $day) {
                ServiceProviderWorkingHour::create([
                    'service_provider_id' => $provider->id,
                    'day_of_week' => $day,
                    'start_time' => '09:00',
                    'end_time' => '17:00',
                ]);
            }

            ServiceProviderWorkingHour::create([
                'service_provider_id' => $provider->id,
                'day_of_week' => 6,
                'start_time' => '10:00',
                'end_time' => '14:00',
            ]);

            $tz = $provider->timezone ?: 'UTC';
            $start = Carbon::now($tz)->addDays(3)->setTime(12, 0)->utc();
            $end = Carbon::now($tz)->addDays(3)->setTime(15, 0)->utc();

            ServiceProviderTimeOff::create([
                'service_provider_id' => $provider->id,
                'starts_at' => $start,
                'ends_at' => $end,
                'is_holiday' => false,
                'reason' => 'Personal time',
            ]);
        }
    }
}
