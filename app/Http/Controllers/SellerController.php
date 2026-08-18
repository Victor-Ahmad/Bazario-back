<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use App\Models\User;
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
            $sellers = Seller::query()
                ->pending()
                ->withActiveUser()
                ->with('user:id,name,email,phone', 'attachments')
                ->select('id', 'user_id', 'store_owner_name', 'store_name', 'address', 'logo', 'description', 'created_at')
                ->orderBy('created_at', 'desc')
                ->paginate(20);
            return $this->successResponse($sellers, 'auth', 'fetched_successfully');
        } catch (\Throwable $e) {

            return $this->errorResponse('fetch_failed', 'auth', 500, [
                'error' => $e->getMessage(),
            ]);
        }
    }
    public function index()
    {
        try {
            $perPage = max(1, min((int) request('per_page', 20), 50));

            $sellers = Seller::query()
                ->approved()
                ->withActiveUser()
                ->with('user:id,name,email,phone')
                ->select('id', 'user_id', 'store_owner_name', 'store_name', 'address', 'logo', 'description', 'created_at')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);


            return $this->successResponse($sellers, 'auth', 'fetched_successfully');
        } catch (\Throwable $e) {

            return $this->errorResponse('fetch_failed', 'auth', 500, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function forceLogoutUser(User $user): void
    {
        $tokenIds = $user->tokens()->pluck('id');

        $user->tokens()->whereIn('id', $tokenIds)->update(['revoked' => true]);

        if ($tokenIds->isNotEmpty()) {
            DB::table('oauth_refresh_tokens')
                ->whereIn('access_token_id', $tokenIds)
                ->update(['revoked' => true]);
        }
    }

    protected function syncApprovedBusinessRoles(User $user): void
    {
        $user->loadMissing(['seller', 'serviceProvider', 'roles']);

        $preservedRoles = $user->getRoleNames()
            ->filter(fn(string $roleName) => !in_array($roleName, ['customer', 'seller', 'service_provider'], true))
            ->values()
            ->all();

        $nextRoles = [...$preservedRoles, 'customer'];

        if ($user->seller?->status === 'approved') {
            $nextRoles[] = 'seller';
        }

        if ($user->serviceProvider?->status === 'approved') {
            $nextRoles[] = 'service_provider';
        }

        $user->syncRoles(array_values(array_unique($nextRoles)));
    }


    public function updateSellerStatus(Request $request, Seller $seller)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);
        try {
            DB::beginTransaction();
            $seller->status = $request->status;
            $seller->save();
            if ($seller->status == 'approved') {
                $role = Role::where('name', 'seller')->where('guard_name', 'web')->first();
                if (!$role) {
                    throw new \Exception(__('auth.role_not_found'));
                }
            }

            $this->syncApprovedBusinessRoles($seller->user);
            $this->forceLogoutUser($seller->user);

            DB::commit();
            return $this->successResponse($seller, 'auth', 'seller_status_updated_successfully');
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->errorResponse('updated_failed', 'auth', 500, [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
