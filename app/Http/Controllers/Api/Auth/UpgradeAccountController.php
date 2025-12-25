<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpgradeToSellerRequest;
use App\Http\Requests\Auth\UpgradeToServiceProviderRequest;
use App\Models\Seller;
use App\Models\ServiceProvider;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Throwable;

class UpgradeAccountController extends Controller
{
    use ApiResponseTrait;

    public function upgradeToSeller(UpgradeToSellerRequest $request)
    {
        try {
            DB::beginTransaction();

            $user     = $request->user();
            $logoPath = null;

            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('sellers/logos', 'public');
            }

            if ($request->filled('email')) {
                $user->email = $request->email;
            }
            if ($request->filled('phone')) {
                $user->phone = $request->phone;
            }
            $user->save();

            if ($user->email == null) {
                return $this->errorResponse(__('this_user_should_add_email'), 'auth', 404);
            }
            if ($user->phone == null) {
                return $this->errorResponse(__('this_user_should_add_phone'), 'auth', 404);
            }

            $seller = Seller::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'store_owner_name' => $request->store_owner_name,
                    'store_name'       => $request->store_name,
                    'address'          => $request->address,
                    'logo'             => $logoPath ? 'storage/' . $logoPath : null,
                    'description'      => $request->description,
                ]
            );

            // Attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $filePath = $file->store('attachments', 'public');
                    $seller->attachments()->create([
                        'file' => 'storage/' . $filePath,
                        'name' => $file->getClientOriginalName(),
                    ]);
                }
            }

            if (!Role::where('name', 'seller')->exists()) {
                throw new \Exception(__('auth.role_not_found'));
            }

            $user->assignRole('seller');
            $user->syncWithoutDetaching(['customer', 'seller']);

            DB::commit();

            return $this->successResponse([
                'user'   => $user,
                'seller' => $seller,
            ], 'auth', 'upgraded_to_seller');
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->errorResponse('upgrade_failed', 'auth', 500, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function upgradeToServiceProvider(UpgradeToServiceProviderRequest $request)
    {
        try {
            DB::beginTransaction();

            $user     = $request->user();
            $logoPath = null;

            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('service_providers/logos', 'public');
            }

            if ($request->filled('email')) {
                $user->email = $request->email;
            }
            if ($request->filled('phone')) {
                $user->phone = $request->phone;
            }
            $user->save();

            if ($user->email == null) {
                return $this->errorResponse(__('this_user_should_add_email'), 'auth', 404);
            }
            if ($user->phone == null) {
                return $this->errorResponse(__('this_user_should_add_phone'), 'auth', 404);
            }

            $serviceProvider = ServiceProvider::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'name'        => $request->name,
                    'address'     => $request->address,
                    'logo'        => $logoPath ? 'storage/' . $logoPath : null,
                    'description' => $request->description,
                ]
            );

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $filePath = $file->store('attachments', 'public');
                    $serviceProvider->attachments()->create([
                        'file' => 'storage/' . $filePath,
                        'name' => $file->getClientOriginalName(),
                    ]);
                }
            }

            if (!Role::where('name', 'service_provider')->exists()) {
                throw new \Exception(__('auth.role_not_found'));
            }

            $user->assignRole('service_provider');
            $user->syncWithoutDetaching(['customer', 'service_provider']);

            DB::commit();

            return $this->successResponse([
                'user'             => $user,
                'service_provider' => $serviceProvider,
            ], 'auth', 'upgraded_to_service_provider');
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->errorResponse('upgrade_failed', 'auth', 500, [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
