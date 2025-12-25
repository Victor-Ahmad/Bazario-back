<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Throwable;

class PasswordUpdateController extends Controller
{
    use ApiResponseTrait;

    public function update(Request $request)
    {
        try {
            $validator = validator($request->all(), [
                'old_password' => 'required',
                'password'     => 'required|confirmed|min:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => 0,
                    'message' => __('auth.validation_failed'),
                    'result'  => ['errors' => $validator->errors()],
                ], 422);
            }

            $user = $request->user();

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
}
