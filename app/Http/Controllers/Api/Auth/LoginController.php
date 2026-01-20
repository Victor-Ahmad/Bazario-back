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

    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return $this->errorResponse(
                'failed',
                'auth',
                401
            );
        }


        $token = $user->createToken('AuthToken')->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'user'  => $user,
            'roles' => $user->getRoleNames(),
        ], 'auth', 'login_success');
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
