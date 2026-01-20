<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Throwable;

class UpgradeRequestController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {
        try {
            $sellers = Seller::with('user:id,name,email,phone', 'attachments')
                ->select(
                    'id',
                    'user_id',
                    'store_owner_name',
                    'store_name',
                    'address',
                    'logo',
                    'description',
                    'status',
                    'created_at'
                )
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->get();

            $serviceProviders = ServiceProvider::with('user:id,name,email,phone', 'attachments')
                ->select(
                    'id',
                    'user_id',
                    'name',
                    'address',
                    'logo',
                    'description',
                    'status',
                    'created_at'
                )
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->successResponse([
                'sellers' => $sellers,
                'service_providers' => $serviceProviders,
            ], 'auth', 'fetched_successfully');
        } catch (Throwable $e) {
            return $this->errorResponse('fetch_failed', 'auth', 500, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function approveSeller(Seller $seller)
    {
        return $this->updateSellerStatus($seller, 'approved');
    }

    public function rejectSeller(Seller $seller)
    {
        return $this->updateSellerStatus($seller, 'rejected');
    }

    public function approveServiceProvider(ServiceProvider $service_provider)
    {
        return $this->updateServiceProviderStatus($service_provider, 'approved');
    }

    public function rejectServiceProvider(ServiceProvider $service_provider)
    {
        return $this->updateServiceProviderStatus($service_provider, 'rejected');
    }

    protected function forceLogoutUser(User $user): void
    {
        $user?->tokens()->delete();
    }

    protected function updateSellerStatus(Seller $seller, string $status)
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            return $this->errorResponse('invalid_status', 'auth', 422);
        }

        try {
            DB::beginTransaction();

            $seller->status = $status;
            $seller->save();

            if ($status === 'approved') {
                $role = Role::where('name', 'seller')
                    ->where('guard_name', 'web')
                    ->first();
                if (!$role) {
                    throw new \Exception(__('auth.role_not_found'));
                }
                $seller->user->assignRole($role);
                $seller->user->syncRoles(['customer', 'seller']);
                $this->forceLogoutUser($seller->user);
            }

            DB::commit();
            return $this->successResponse($seller, 'auth', 'seller_status_updated_successfully');
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->errorResponse('updated_failed', 'auth', 500, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function updateServiceProviderStatus(ServiceProvider $service_provider, string $status)
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            return $this->errorResponse('invalid_status', 'auth', 422);
        }

        try {
            DB::beginTransaction();

            $service_provider->status = $status;
            $service_provider->save();

            if ($status === 'approved') {
                $role = Role::where('name', 'service_provider')
                    ->where('guard_name', 'web')
                    ->first();
                if (!$role) {
                    throw new \Exception(__('auth.role_not_found'));
                }
                $service_provider->user->assignRole($role);
                $service_provider->user->syncRoles(['customer', 'service_provider']);
                $this->forceLogoutUser($service_provider->user);
            }

            DB::commit();
            return $this->successResponse($service_provider, 'auth', 'service_provider_status_updated_successfully');
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->errorResponse('updated_failed', 'auth', 500, [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
