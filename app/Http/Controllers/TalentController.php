<?php

namespace App\Http\Controllers;


use App\Models\Talent;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;
use Spatie\Permission\Models\Role;

class TalentController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {

        try {
            $talents = Talent::with('user:id,name,email,phone')
                ->select('id', 'user_id', 'name', 'address', 'logo', 'description', 'created_at')
                ->where('status', 'approved')
                ->orderBy('created_at', 'desc')
                ->paginate(20);


            return $this->successResponse($talents, 'auth', 'fetched_successfully.');
        } catch (\Throwable $e) {

            return $this->errorResponse('fetch_failed', 'auth', 500, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function requests()
    {
        try {
            $talents = Talent::with('user:id,name,email,phone', 'attachments')
                ->select('id', 'user_id', 'name', 'address', 'logo', 'description', 'created_at')
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->paginate(20);
            return $this->successResponse($talents, 'auth', 'fetched_successfully.');
        } catch (\Throwable $e) {

            return $this->errorResponse('fetch_failed', 'auth', 500, [
                'error' => $e->getMessage(),
            ]);
        }
    }


    public function updateTalentStatus(Request $request, Talent $talent)
    {
        $request->validate([
            'status' => 'required|in:accepted,rejected',
        ]);
        try {
            DB::beginTransaction();
            $talent->status = $request->status;
            $talent->save();
            $role = Role::where('name', 'talent')->where('guard_name', 'api')->first();

            if (!$role) {
                throw new \Exception(__('auth.role_not_found'));
            }

            $talent->user->assignRole($role);

            DB::commit();
            return $this->successResponse($talent, 'auth', 'talent_status_updated_successfully.');
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->errorResponse('updated_failed', 'auth', 500, [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
