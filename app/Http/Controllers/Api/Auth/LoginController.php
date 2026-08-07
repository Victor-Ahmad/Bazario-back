<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    use ApiResponseTrait;

    protected function attemptLogin(array $credentials, bool $allowAdmin = false)
    {
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return $this->errorResponse(
                'failed',
                'auth',
                401
            );
        }

        if ($user->hasRole('admin') && ! $allowAdmin) {
            return $this->errorResponse(
                'admin_marketplace_forbidden',
                'auth',
                403
            );
        }

        $token = $user->createToken('AuthToken')->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'user'  => $user,
            'roles' => $user->getRoleNames(),
        ], 'auth', 'login_success');
    }

    public function login(LoginRequest $request)
    {
        return $this->attemptLogin($request->validated(), false);
    }

    public function demoCsrf(Request $request)
    {
        $request->session()->regenerateToken();

        return response()->json([
            'csrf_token' => csrf_token(),
        ]);
    }

    public function demoLogin(LoginRequest $request)
    {
        return $this->attemptLogin($request->validated(), true);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            $user->tokens()
                ->where('id', $user->currentAccessToken()->id)
                ->delete();
        }

        return $this->successResponse([], 'auth', 'logout_success');
    }


    public function logoutAll(Request $request)
    {
        $request->user()?->tokens()->delete();

        return $this->successResponse([], 'auth', 'logout_all_success');
    }
}
