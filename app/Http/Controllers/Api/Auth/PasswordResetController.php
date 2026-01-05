<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\OtpCode;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PasswordResetController extends Controller
{
    use ApiResponseTrait;

    public function sendResetOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return $this->errorResponse('email_not_found', 'auth', 404);
        }

        // $otp = rand(100000, 999999);
        $otp   = '111111'; // TODO: change in production
        $token = uniqid('reset_', true);

        OtpCode::create([
            'email'      => $user->email,
            'otp'        => $otp,
            'token'      => $token,
            'expires_at' => now()->addMinutes(10),
            'is_used'    => false,
        ]);

        // Mail::to($user->email)->send(new ResetOtpMail($otp));

        return $this->successResponse([
            'message' => __('auth.otp_sent'),
        ], 'auth');
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|numeric',
        ]);

        $otpRecord = OtpCode::where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otpRecord) {
            return $this->errorResponse('invalid_otp', 'auth', 400);
        }

        return $this->successResponse([
            'token' => $otpRecord->token,
        ], 'auth', 'otp_verified');
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'token'    => 'required',
            'password' => 'required|min:6',
        ]);

        $otpRecord = OtpCode::where('email', $request->email)
            ->where('token', $request->token)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otpRecord) {
            return $this->errorResponse('invalid_token', 'auth', 400);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return $this->errorResponse('email_not_found', 'auth', 404);
        }

        $user->update(['password' => Hash::make($request->password)]);
        $otpRecord->delete();

        return $this->successResponse([], 'auth', 'password_reset_success');
    }
}
