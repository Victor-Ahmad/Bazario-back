<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;
use Spatie\Permission\Models\Role;

class SellerController extends Controller
{
    use ApiResponseTrait;


    public function requests()
    {
        try {
            $sellers = Seller::with('user:id,name,email,phone')
                ->select('id', 'user_id', 'store_owner_name', 'store_name', 'address', 'logo', 'description', 'created_at')
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->paginate(20);
            return $this->successResponse($sellers, 'auth', 'fetched_successfully.');
        } catch (\Throwable $e) {

            return $this->errorResponse('fetch_failed', 'auth', 500, [
                'error' => $e->getMessage(),
            ]);
        }
    }
    public function index()
    {
        try {
            $sellers = Seller::with('user:id,name,email,phone')
                ->select('id', 'user_id', 'store_owner_name', 'store_name', 'address', 'logo', 'description', 'created_at')
                ->where('status', 'accepted')
                ->orderBy('created_at', 'desc')
                ->paginate(20);


            return $this->successResponse($sellers, 'auth', 'fetched_successfully.');
        } catch (\Throwable $e) {

            return $this->errorResponse('fetch_failed', 'auth', 500, [
                'error' => $e->getMessage(),
            ]);
        }
    }
    public function updateSellerStatus(Request $request, Seller $seller)
    {
        $request->validate([
            'status' => 'required|in:accepted,rejected',
        ]);
        try {
            DB::beginTransaction();
            $seller->status = $request->status;
            $seller->save();
            $role = null;
            if ($seller->status == 'accepted') {
                $role = Role::where('name', 'seller')->where('guard_name', 'api')->first();
            } else {
                $role = Role::where('name', 'customer')->where('guard_name', 'api')->first();
            }

            if (!$role) {
                throw new \Exception(__('auth.role_not_found'));
            }

            $seller->user->assignRole($role);

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
