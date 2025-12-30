<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpgradeToSellerRequest;
use App\Http\Requests\Auth\UpgradeToServiceProviderRequest;
use App\Models\Seller;
use App\Models\ServiceProvider;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Throwable;

class UpgradeAccountController extends Controller
{
    use ApiResponseTrait;

    public function upgradeToSeller(UpgradeToSellerRequest $request)
    {
        $storedFiles = [];

        try {
            $result = DB::transaction(function () use ($request, &$storedFiles) {

                $user     = $request->user();
                $logoPath = null;


                if ($request->hasFile('logo')) {
                    $logoPath = $request->file('logo')->store('sellers/logos', 'public');
                    $storedFiles[] = $logoPath;
                }

                if ($request->filled('email')) {
                    $user->email = $request->email;
                }
                if ($request->filled('phone')) {
                    $user->phone = $request->phone;
                }
                $user->save();



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

                if ($request->hasFile('attachments')) {
                    foreach ($request->file('attachments') as $file) {
                        $filePath = $file->store('attachments', 'public');
                        $storedFiles[] = $filePath;

                        $seller->attachments()->create([
                            'file' => 'storage/' . $filePath,
                            'name' => $file->getClientOriginalName(),
                        ]);
                    }
                }

                if (! Role::where('name', 'seller')->exists()) {
                    throw new \Exception(__('auth.role_not_found'));
                }

                $user->assignRole('seller');
                $user->syncRoles(['customer', 'seller']);

                return [
                    'user'   => $user,
                    'seller' => $seller,
                ];
            });

            return $this->successResponse($result, 'auth', 'upgraded_to_seller');
        } catch (Throwable $e) {
            foreach ($storedFiles as $path) {
                Storage::disk('public')->delete($path);
            }

            return $this->errorResponse('upgrade_failed', 'auth', 500, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function upgradeToServiceProvider(UpgradeToServiceProviderRequest $request)
    {
        $storedFiles = [];
        try {
            $result = DB::transaction(function () use ($request, &$storedFiles) {

                $user     = $request->user();
                $logoPath = null;

                if ($request->hasFile('logo')) {
                    $logoPath = $request->file('logo')->store('service_providers/logos', 'public');
                    $storedFiles[] = $logoPath;
                }

                if ($request->filled('email')) {
                    $user->email = $request->email;
                }
                if ($request->filled('phone')) {
                    $user->phone = $request->phone;
                }
                $user->save();

                if ($user->email == null) {
                    throw new \Exception(__('this_user_should_add_email'), 404);
                }
                if ($user->phone == null) {
                    throw new \Exception(__('this_user_should_add_phone'), 404);
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
                        $storedFiles[] = $filePath;

                        $serviceProvider->attachments()->create([
                            'file' => 'storage/' . $filePath,
                            'name' => $file->getClientOriginalName(),
                        ]);
                    }
                }

                if (! Role::where('name', 'service_provider')->exists()) {
                    throw new \Exception(__('auth.role_not_found'));
                }

                $user->assignRole('service_provider');
                $user->syncRoles(['customer', 'service_provider']);

                return [
                    'user'             => $user,
                    'service_provider' => $serviceProvider,
                ];
            });

            return $this->successResponse($result, 'auth', 'upgraded_to_service_provider');
        } catch (Throwable $e) {
            foreach ($storedFiles as $path) {
                Storage::disk('public')->delete($path);
            }

            if ((int) $e->getCode() === 404) {
                return $this->errorResponse($e->getMessage(), 'auth', 404);
            }

            return $this->errorResponse('upgrade_failed', 'auth', 500, [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
