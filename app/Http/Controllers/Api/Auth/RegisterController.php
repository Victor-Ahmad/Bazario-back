<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CustomerRegisterRequest;
use App\Http\Requests\Auth\UpgradeToSellerRequest;
use App\Http\Requests\Auth\UpgradeToServiceProviderRequest;
use App\Models\OtpCode;
use App\Models\Seller;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Throwable;
use Exception;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    use ApiResponseTrait;

    public function register(CustomerRegisterRequest $request)
    {
        try {
            DB::beginTransaction();
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'age' => $request->age,
            ]);
            if (!Role::where('name', 'customer')->exists()) {
                throw new Exception(__('auth.role_not_found'));
            }
            $user->assignRole(Role::where('name', 'customer')->where('guard_name', 'api')->first());

            $token = $user->createToken('CustomerToken')->plainTextToken;

            DB::commit();

            return $this->successResponse([
                'token' => $token,
                'user' => $user,
                'role' => $user->getRoleNames()->first(),
            ], 'auth', 'registered');
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->errorResponse('registration_failed', 'auth', 500, [
                'error' => $e->getMessage(),
            ]);
        }
    }


    public function upgradeToSeller(UpgradeToSellerRequest $request)
    {
        try {
            DB::beginTransaction();

            $user = auth()->guard()->user();
            $logoPath = null;

            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('sellers/logos', 'public');
            }
            if ($request->filled('email')) {
                $user->email = $request->email;
            }
            if ($request->filled('phone')) {
                $user->phone = $request->phone;
            }
            $user->save();
            if ($user->email == null) {
                return $this->errorResponse(__('this_user_should_add_email'), 'auth', 404);
            }
            if ($user->phone == null) {
                return $this->errorResponse(__('this_user_should_add_phone'), 'auth', 404);
            }

            $seller = Seller::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'store_owner_name' => $request->store_owner_name,
                    'store_name' => $request->store_name,
                    'address' => $request->address,
                    'logo' => $logoPath ? 'storage/' . $logoPath : null,
                    'description' => $request->description,
                ]
            );

            // Attachments logic
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $filePath = $file->store('attachments', 'public');
                    $seller->attachments()->create([
                        'file' => 'storage/' . $filePath,
                        'name' => $file->getClientOriginalName(),
                    ]);
                }
            }

            if (!Role::where('name', 'seller')->exists()) {
                throw new \Exception(__('auth.role_not_found'));
            }

            DB::commit();

            return $this->successResponse([
                'user' => $user,
                'seller' => $seller,
            ], 'auth', 'upgraded_to_seller');
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->errorResponse('upgrade_failed', 'auth', 500, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function upgradeToServiceProvider(UpgradeToServiceProviderRequest $request)
    {
        try {
            DB::beginTransaction();

            $user = auth()->guard()->user();
            $logoPath = null;

            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('service_providers/logos', 'public');
            }
            if ($request->filled('email')) {
                $user->email = $request->email;
            }
            if ($request->filled('phone')) {
                $user->phone = $request->phone;
            }
            $user->save();
            if ($user->email == null) {
                return $this->errorResponse(__('this_user_should_add_email'), 'auth', 404);
            }
            if ($user->phone == null) {
                return $this->errorResponse(__('this_user_should_add_phone'), 'auth', 404);
            }

            $service_provider = ServiceProvider::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $request->name,
                    'address' => $request->address,
                    'logo' => $logoPath ? 'storage/' . $logoPath : null,
                    'description' => $request->description,
                ]
            );

            // Attachments logic
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $filePath = $file->store('attachments', 'public');
                    $service_provider->attachments()->create([
                        'file' => 'storage/' . $filePath,
                        'name' => $file->getClientOriginalName(),
                    ]);
                }
            }

            if (!Role::where('name', 'service_provider')->exists()) {
                throw new \Exception(__('auth.role_not_found'));
            }

            DB::commit();

            return $this->successResponse([
                'user' => $user,
                'service_provider' => $service_provider,
            ], 'auth', 'upgraded_to_service_provider');
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->errorResponse('upgrade_failed', 'auth', 500, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            return $this->errorResponse(__('user_not_found'), 'auth', 404);
        }

        if (!Hash::check($credentials['password'], $user->password)) {
            return $this->errorResponse(__('invalid_password'), 'auth', 401);
        }
        $token = $user->createToken('AuthToken')->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'user' => $user,
            'role' => $user->getRoleNames()->first(),
        ], 'auth', 'login_success');
    }
    public function updatePassword(Request $request)
    {
        try {
            $validator = validator($request->all(), [
                'old_password' => 'required',
                'password' => 'required|confirmed|min:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => 0,
                    'message' => __('auth.validation_failed'),
                    'result' => ['errors' => $validator->errors()],
                ], 422);
            }

            $user = auth()->guard()->user();

            if (!Hash::check($request->old_password, $user->password)) {
                return $this->errorResponse('invalid_old_password', 'auth', 400);
            }

            $user->update(['password' => Hash::make($request->password)]);

            return $this->successResponse([], 'auth', 'password_updated');
        } catch (Throwable $e) {
            return $this->errorResponse('update_password_failed', 'auth', 500, [
                'error' => $e->getMessage(),
            ]);
        }
    }
    public function sendResetOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return $this->errorResponse('email_not_found', 'auth', 404);
        }
        //  $otp = rand(100000, 999999);
        $otp = '111111';
        $token = uniqid('reset_', true);

        OtpCode::create([
            'email' => $user->email,
            'otp' => $otp,
            'token' => $token,
            'expires_at' => now()->addMinutes(10),
            'is_used' => false,

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
            'otp' => 'required|numeric',
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
            'token' => $otpRecord->token
        ], 'auth', 'otp_verified');
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required',
            'password' => 'required|confirmed|min:6',
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

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            $user->tokens()->where('id', $user->currentAccessToken()?->id)->delete();
        }

        return $this->successResponse([], 'auth', 'logout_success');
    }
}
