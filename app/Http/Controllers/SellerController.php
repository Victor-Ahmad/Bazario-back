<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class SellerController extends Controller
{
    use ApiResponseTrait;

    public function updateSellerStatus(Request $request, Seller $seller)
    {
        $request->validate([
            'status' => 'required|in:accepted,rejected',
        ]);
        try {
            DB::beginTransaction();
            $seller->status = $request->status;
            $seller->save();
            DB::commit();
            return $this->successResponse($seller, 'auth', 'seller_status_updated_successfully.');
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->errorResponse('updated_failed', 'auth', 500, [
                'error' => $e->getMessage(),
            ]);
        }
    }
}