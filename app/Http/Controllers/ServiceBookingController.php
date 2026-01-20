<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\ServiceProvider;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceBookingController extends Controller
{
    public function store(Request $request, Service $service)
    {
        $user = $request->user();

        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'ends_at' => ['nullable', 'date'],
            'location_type' => ['nullable', 'string', 'max:32'],
            'location_payload' => ['nullable', 'array'],
        ]);

        if (!$service->is_active) {
            return response()->json([
                'message' => __('bookings.service_not_active'),
            ], 422);
        }

        $provider = ServiceProvider::query()
            ->whereKey($service->provider_id)
            ->with(['workingHours', 'timeOffs'])
            ->first();

        if (!$provider) {
            return response()->json([
                'message' => __('bookings.service_provider_not_found'),
            ], 422);
        }

        $providerUserId = $provider->user_id;
        if (!$providerUserId) {
            return response()->json([
                'message' => __('bookings.provider_no_user'),
            ], 422);
        }

        // Prefer provider timezone unless client explicitly sends one
        $tz = $data['timezone'] ?? $provider->timezone ?? 'UTC';

        // Convert to UTC for storage
        $startsAtUtc = Carbon::parse($data['starts_at'], $tz)->utc();

        // Compute end
        if (!empty($data['ends_at'])) {
            $endsAtUtc = Carbon::parse($data['ends_at'], $tz)->utc();
        } else {
            $duration = (int) ($service->duration_minutes ?? 60);
            $endsAtUtc = $startsAtUtc->copy()->addMinutes($duration);
        }

        if ($endsAtUtc->lessThanOrEqualTo($startsAtUtc)) {
            return response()->json([
                'message' => __('bookings.invalid_time_range'),
            ], 422);
        }

        $booking = DB::transaction(function () use ($service, $provider, $providerUserId, $user, $startsAtUtc, $endsAtUtc, $data, $tz) {
            // Serialize booking attempts per provider to prevent race conditions
            ServiceProvider::whereKey($provider->id)->lockForUpdate()->first();

            // 1) Check within working hours (provider local time)
            $startLocal = $startsAtUtc->copy()->tz($tz);
            $endLocal = $endsAtUtc->copy()->tz($tz);

            $dow = (int) $startLocal->dayOfWeek; // 0=Sun..6=Sat
            $dayHours = $provider->workingHours->where('day_of_week', $dow);

            if ($dayHours->isEmpty()) {
                abort(422, __('bookings.provider_not_available_day'));
            }

            $fits = false;
            foreach ($dayHours as $wh) {
                $whStart = Carbon::parse($startLocal->toDateString() . ' ' . $wh->start_time, $tz);
                $whEnd   = Carbon::parse($startLocal->toDateString() . ' ' . $wh->end_time, $tz);

                if ($startLocal->gte($whStart) && $endLocal->lte($whEnd)) {
                    $fits = true;
                    break;
                }
            }

            if (!$fits) {
                abort(422, __('bookings.outside_working_hours'));
            }

            // 2) Check time off (stored in UTC)
            $timeOffOverlap = $provider->timeOffs
                ->contains(fn($t) => $t->starts_at < $endsAtUtc && $t->ends_at > $startsAtUtc);

            if ($timeOffOverlap) {
                abort(422, __('bookings.provider_time_off'));
            }

            // 3) Capacity check (per service)
            $capacity = (int) ($service->max_concurrent_bookings ?? 1);

            $overlappingCount = ServiceBooking::query()
                ->where('provider_user_id', $providerUserId)
                ->where('service_id', $service->id)
                ->whereIn('status', ['requested', 'confirmed', 'in_progress'])
                ->where('starts_at', '<', $endsAtUtc)
                ->where('ends_at', '>', $startsAtUtc)
                ->count();

            if ($overlappingCount >= $capacity) {
                abort(422, __('bookings.slot_unavailable'));
            }

            // 4) Create booking
            return ServiceBooking::create([
                'order_item_id' => null,
                'service_id' => $service->id,
                'provider_user_id' => $providerUserId,
                'customer_user_id' => $user->id,
                'status' => 'requested',
                'starts_at' => $startsAtUtc,
                'ends_at' => $endsAtUtc,
                'timezone' => $tz,
                'location_type' => $data['location_type'] ?? $service->location_type,
                'location_payload' => $data['location_payload'] ?? null,
                'cancelled_at' => null,
                'cancellation_reason' => null,
            ]);
        });

        return response()->json($booking->load('service'));
    }

    public function myBookings(Request $request)
    {
        $user = $request->user();

        $bookings = ServiceBooking::query()
            ->where('customer_user_id', $user->id)
            ->with(['service', 'providerUser'])
            ->orderBy('starts_at')
            ->paginate(20);

        return response()->json($bookings);
    }

    public function providerBookings(Request $request)
    {
        $user = $request->user();

        $bookings = ServiceBooking::query()
            ->where('provider_user_id', $user->id)
            ->with(['service', 'customerUser'])
            ->orderBy('starts_at')
            ->paginate(20);

        return response()->json($bookings);
    }

    public function confirm(Request $request, ServiceBooking $booking)
    {
        $user = $request->user();
        abort_unless($booking->provider_user_id === $user->id, 403);

        if (!in_array($booking->status, ['requested'], true)) {
            return response()->json([
                'message' => __('bookings.confirm_not_allowed'),
            ], 422);
        }

        $booking->status = 'confirmed';
        $booking->save();

        return response()->json($booking);
    }

    public function cancel(Request $request, ServiceBooking $booking)
    {
        $user = $request->user();

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $isProvider = $booking->provider_user_id === $user->id;
        $isCustomer = $booking->customer_user_id === $user->id;
        abort_unless($isProvider || $isCustomer, 403);

        if (in_array($booking->status, ['completed', 'cancelled_by_customer', 'cancelled_by_provider'], true)) {
            return response()->json([
                'message' => __('bookings.already_finalized'),
            ], 422);
        }

        $booking->status = $isProvider ? 'cancelled_by_provider' : 'cancelled_by_customer';
        $booking->cancelled_at = now();
        $booking->cancellation_reason = $data['reason'] ?? null;
        $booking->save();

        return response()->json($booking);
    }

    public function complete(Request $request, ServiceBooking $booking)
    {
        $user = $request->user();
        abort_unless($booking->provider_user_id === $user->id, 403);

        if (!in_array($booking->status, ['confirmed', 'in_progress'], true)) {
            return response()->json([
                'message' => __('bookings.complete_not_allowed'),
            ], 422);
        }

        $booking->status = 'completed';
        $booking->save();

        return response()->json($booking);
    }
}
