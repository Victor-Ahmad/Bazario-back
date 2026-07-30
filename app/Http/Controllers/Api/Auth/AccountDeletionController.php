<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class AccountDeletionController extends Controller
{
    use ApiResponseTrait;

    public function destroy(Request $request)
    {
        $validator = validator($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => 0,
                'message' => __('auth.validation_failed'),
                'result' => ['errors' => $validator->errors()],
            ], 422);
        }

        $user = $request->user();

        if (!$user) {
            return $this->errorResponse('unauthorized', 'auth', 401);
        }

        if (!Hash::check($request->string('password')->toString(), $user->password)) {
            return $this->errorResponse('invalid_password', 'auth', 400);
        }

        try {
            DB::transaction(function () use ($user) {
                $user->tokens()->delete();

                $deletedEmail = $user->email;
                $userId = $user->id;
                $timestamp = now()->format('YmdHis');

                $user->forceFill([
                    'deleted_account_email' => $deletedEmail,
                    'email' => $deletedEmail ? sprintf('deleted+%d+%s@bazario.local', $userId, $timestamp) : null,
                    'phone' => null,
                ])->save();

                $user->delete();
            });

            return $this->successResponse([], 'auth', 'deleted_successfully');
        } catch (Throwable $e) {
            return $this->errorResponse('delete_account_failed', 'auth', 500, [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
