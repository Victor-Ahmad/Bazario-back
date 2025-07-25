<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CustomerRegisterRequest;
use App\Http\Requests\Auth\SellerRegisterRequest;
use App\Http\Requests\Auth\TalentRegisterRequest;
use App\Http\Requests\Auth\UpgradeToSellerRequest;
use App\Http\Requests\Auth\UpgradeToTalentRequest;
use App\Models\OtpCode;
use App\Models\Seller;
use App\Models\Talent;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Laravel\Socialite\Facades\Socialite;
use Throwable;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    use ApiResponseTrait;

    public function customerRegister(CustomerRegisterRequest $request)
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

            $token = $user->createToken('CustomerToken')->accessToken;

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
    public function sellerRegister(SellerRegisterRequest $request)
    {
        try {
            DB::beginTransaction();

            $logoPath = null;
            if ($request->hasFile('logo')) {
                $logoPath =  $request->file('logo')->store('sellers/logos', 'public');
            }


            $user = User::create([
                'name'     => $request->store_owner_name,
                'email'    => $request->email,
                'phone'    => $request->phone,
                'password' => Hash::make($request->password),
            ]);


            $seller = Seller::create([
                'user_id'          => $user->id,
                'store_owner_name' => $request->store_owner_name,
                'store_name'       => $request->store_name,
                'address'          => $request->address,
                'logo'             => $logoPath ? 'storage/' . $logoPath : null,
                'description'      => $request->description,

            ]);


            $role = Role::where('name', 'customer')->where('guard_name', 'api')->first();

            if (!$role) {
                throw new \Exception(__('auth.role_not_found'));
            }

            $user->assignRole($role);


            $token = $user->createToken('customerToken')->accessToken;

            DB::commit();

            return $this->successResponse([
                'token'  => $token,
                'user'   => $user,
                'role' => $user->getRoleNames()->first(),
                'seller' => $seller,
            ], 'auth', 'registered');
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->errorResponse('registration_failed', 'auth', 500, [
                'error' => $e->getMessage(),
            ]);
        }
    }
    public function talentRegister(TalentRegisterRequest $request)
    {
        try {
            DB::beginTransaction();

            $logoPath = null;
            if ($request->hasFile('logo')) {
                $logoPath =  $request->file('logo')->store('talents/logos', 'public');
            }


            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'phone'    => $request->phone,
                'password' => Hash::make($request->password),
            ]);


            $talent = Talent::create([
                'user_id'          => $user->id,
                'name'             => $request->name,
                'address'          => $request->address,
                'logo'             => $logoPath ? 'storage/' . $logoPath : null,
                'description'      => $request->description,

            ]);


            $role = Role::where('name', 'customer')->where('guard_name', 'api')->first();

            if (!$role) {
                throw new \Exception(__('auth.role_not_found'));
            }

            $user->assignRole($role);


            $token = $user->createToken('customerToken')->accessToken;

            DB::commit();

            return $this->successResponse([
                'token'  => $token,
                'user'   => $user,
                'role' => $user->getRoleNames()->first(),
                'talent' => $talent,
            ], 'auth', 'registered');
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->errorResponse('registration_failed', 'auth', 500, [
                'error' => $e->getMessage(),
            ]);
        }
    }
    public function guestRegister()
    {
        $user = User::create([
            'name' => 'Guest-' . uniqid(),
            'email' => null,
            'password' => Hash::make(Str::random(12)),
        ]);
        $role = Role::where('name', 'guest')->where('guard_name', 'api')->first();

        if (!$role) {
            throw new \Exception(__('auth.role_not_found'));
        }
        $user->assignRole($role);

        $token = $user->createToken('GuestToken')->accessToken;

        return $this->successResponse([
            'token' => $token,
            'user'  => $user,
        ], 'auth', 'guest_registered');
    }

    public function socialRegister($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();

            $user = User::firstOrCreate([
                'email' => $socialUser->getEmail(),
            ], [
                'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'Social User',
                'password' => Hash::make(uniqid()),
                'provider_id' => $socialUser->getId(),
                'provider' => $provider,
            ]);

            if (!$user->hasRole('customer')) {
                $user->assignRole('customer');
            }

            $token = $user->createToken('SocialCustomerToken')->accessToken;

            return $this->successResponse([
                'token' => $token,
                'user' => $user,
            ], 'auth', 'registered');
        } catch (Throwable $e) {
            return $this->errorResponse('registration_failed', 'auth', 500, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function upgradeToSeller(UpgradeToSellerRequest $request)
    {
        try {
            DB::beginTransaction();

            $user = auth()->user();
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

    public function upgradeToTalent(UpgradeToTalentRequest $request)
    {
        try {
            DB::beginTransaction();

            $user = auth()->user();
            $logoPath = null;

            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('talents/logos', 'public');
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

            $talent = Talent::updateOrCreate(
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
                    $talent->attachments()->create([
                        'file' => 'storage/' . $filePath,
                        'name' => $file->getClientOriginalName(),
                    ]);
                }
            }

            if (!Role::where('name', 'talent')->exists()) {
                throw new \Exception(__('auth.role_not_found'));
            }

            DB::commit();

            return $this->successResponse([
                'user' => $user,
                'talent' => $talent,
            ], 'auth', 'upgraded_to_talent');
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
        Auth::login($user);
        $token = $user->createToken('AuthToken')->accessToken;

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

            $user = auth()->user();

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
}
