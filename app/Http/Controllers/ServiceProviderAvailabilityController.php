<?php

namespace App\Http\Controllers;

use App\Models\ServiceProvider;
use App\Models\ServiceProviderTimeOff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ServiceProviderAvailabilityController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $provider = ServiceProvider::where('user_id', $user->id)
            ->with(['workingHours', 'timeOffs'])
            ->firstOrFail();

        return response()->json($provider);
    }

    public function updateWorkingHours(Request $request)
    {
        $user = $request->user();
        $provider = ServiceProvider::where('user_id', $user->id)->firstOrFail();

        $data = $request->validate([
            'timezone' => ['nullable', 'string', 'max:64'],
            'days' => ['required', 'array'],
            'days.*.day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'days.*.intervals' => ['required', 'array'],
            'days.*.intervals.*.start_time' => ['required', 'date_format:H:i'],
            'days.*.intervals.*.end_time' => ['required', 'date_format:H:i'],
        ]);

        return DB::transaction(function () use ($provider, $data) {
            if (!empty($data['timezone'])) {
                $provider->timezone = $data['timezone'];
                $provider->save();
            }

            // Replace strategy 
            $provider->workingHours()->delete();

            foreach ($data['days'] as $day) {
                foreach ($day['intervals'] as $interval) {

                    if ($interval['end_time'] <= $interval['start_time']) {
                        abort(422, 'end_time must be after start_time');
                    }

                    $provider->workingHours()->create([
                        'day_of_week' => (int)$day['day_of_week'],
                        'start_time' => $interval['start_time'],
                        'end_time' => $interval['end_time'],
                    ]);
                }
            }

            return response()->json(['message' => 'Working hours updated']);
        });
    }

    public function addTimeOff(Request $request)
    {
        $user = $request->user();
        $provider = ServiceProvider::where('user_id', $user->id)->firstOrFail();

        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'is_holiday' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $tz = $data['timezone'] ?? $provider->timezone ?? 'UTC';

        $startsUtc = Carbon::parse($data['starts_at'], $tz)->utc();
        $endsUtc = Carbon::parse($data['ends_at'], $tz)->utc();

        if ($endsUtc->lessThanOrEqualTo($startsUtc)) {
            return response()->json(['message' => 'Invalid time range'], 422);
        }

        $timeOff = ServiceProviderTimeOff::create([
            'service_provider_id' => $provider->id,
            'starts_at' => $startsUtc,
            'ends_at' => $endsUtc,
            'is_holiday' => (bool)($data['is_holiday'] ?? false),
            'reason' => $data['reason'] ?? null,
        ]);

        return response()->json($timeOff);
    }

    public function deleteTimeOff(Request $request, ServiceProviderTimeOff $timeOff)
    {
        $user = $request->user();
        $provider = ServiceProvider::where('user_id', $user->id)->firstOrFail();

        abort_unless($timeOff->service_provider_id === $provider->id, 403);

        $timeOff->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
