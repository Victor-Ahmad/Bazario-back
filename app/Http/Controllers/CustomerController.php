<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {
        try {
            $customers = User::role('customer')
                ->select('id', 'name', 'email', 'phone', 'created_at')
                ->orderBy('created_at', 'desc')
                ->paginate(20);
            return $this->successResponse($customers, 'auth', 'fetched_successfully');
        } catch (\Throwable $e) {

            return $this->errorResponse('fetch_failed', 'auth', 500, [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
