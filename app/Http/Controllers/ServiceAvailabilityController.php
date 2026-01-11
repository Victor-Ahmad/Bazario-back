<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceBooking;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ServiceAvailabilityController extends Controller
{
    public function day(Request $request, Service $service)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        $provider = $service->serviceProvider()->with(['workingHours', 'timeOffs'])->firstOrFail();
        $tz = $data['timezone'] ?? $provider->timezone ?? 'UTC';

        $dateLocal = Carbon::createFromFormat('Y-m-d', $data['date'], $tz)->startOfDay();

        $dow = (int) $dateLocal->dayOfWeek; // 0=Sun..6=Sat

        $intervals = $provider->workingHours->where('day_of_week', $dow)->values();
        if ($intervals->isEmpty()) {
            return response()->json([
                'date' => $data['date'],
                'timezone' => $tz,
                'slots' => [],
            ]);
        }

        $duration = (int) ($service->duration_minutes ?? 60);
        $step = (int) ($service->slot_interval_minutes ?? 15);
        $capacity = (int) ($service->max_concurrent_bookings ?? 1);

        // Fetch active bookings for this provider+service for that day (UTC range)
        $dayStartUtc = $dateLocal->copy()->utc();
        $dayEndUtc = $dateLocal->copy()->addDay()->utc();

        $activeBookings = ServiceBooking::query()
            ->where('provider_user_id', $provider->user_id)
            ->where('service_id', $service->id)
            ->whereIn('status', ['requested', 'confirmed', 'in_progress'])
            ->where('starts_at', '<', $dayEndUtc)
            ->where('ends_at', '>', $dayStartUtc)
            ->get(['starts_at', 'ends_at']);

        // Time offs that overlap this day
        $timeOffs = $provider->timeOffs
            ->filter(fn($t) => $t->starts_at < $dayEndUtc && $t->ends_at > $dayStartUtc)
            ->values();

        $slots = [];

        foreach ($intervals as $wh) {
            $startLocal = $dateLocal->copy()->setTimeFromTimeString($wh->start_time);
            $endLocal = $dateLocal->copy()->setTimeFromTimeString($wh->end_time);

            for ($cursor = $startLocal->copy(); $cursor->copy()->addMinutes($duration)->lte($endLocal); $cursor->addMinutes($step)) {
                $slotStartLocal = $cursor->copy();
                $slotEndLocal = $cursor->copy()->addMinutes($duration);

                $slotStartUtc = $slotStartLocal->copy()->utc();
                $slotEndUtc = $slotEndLocal->copy()->utc();

                // Exclude time off
                $blocked = $timeOffs->contains(fn($t) => $t->starts_at < $slotEndUtc && $t->ends_at > $slotStartUtc);
                if ($blocked) {
                    continue;
                }

                // Capacity check: how many bookings overlap this slot?
                $used = $activeBookings->filter(fn($b) => $b->starts_at < $slotEndUtc && $b->ends_at > $slotStartUtc)->count();
                $remaining = $capacity - $used;

                if ($remaining <= 0) {
                    continue;
                }

                $slots[] = [
                    'starts_at' => $slotStartLocal->toIso8601String(),
                    'ends_at' => $slotEndLocal->toIso8601String(),
                    'remaining_capacity' => $remaining,
                ];
            }
        }

        return response()->json([
            'date' => $data['date'],
            'timezone' => $tz,
            'duration_minutes' => $duration,
            'slot_interval_minutes' => $step,
            'capacity' => $capacity,
            'slots' => $slots,
        ]);
    }
}
