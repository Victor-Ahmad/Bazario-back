<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CustomerRegisterRequest;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;


class RegisterController extends Controller
{
    use ApiResponseTrait;

    public function register(CustomerRegisterRequest $request)
    {

        $user = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $data['password'] = Hash::make($data['password']);

            $user = User::create($data);

            $user->assignRole('customer');

            return $user;
        });

        $token = $user->createToken('CustomerToken')->plainTextToken;
        // $token = $user->createToken('CustomerToken', ['*'], now()->addWeeks(1))->plainTextToken;


        return $this->successResponse([
            'token'      => $token,
            'token_type' => 'Bearer',
            'user'       => $user,
            'role'       => $user->getRoleNames()->first(),
        ], 'auth', 'registered');
    }
}
