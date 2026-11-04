<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Role;
use App\Models\RoleType;
use Illuminate\Support\Facades\Auth;

class RoleTypeController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return mixed
     */
    public function index()
    {
        $vars['roles'] = Role::where('guard_name', 'tour_operator_staff')->whereNotIn('name', ['admin', 'super-admin', 'affiliate'])->get();
        $vars['page_title'] = 'Role Types';

        return view('users.admin.role-types', $vars);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param Request $request
     * @return mixed
     */
    public function fetchRoleTypes(Request $request)
    {
        try {
            $roleId = $request->get('role_id');
            $roleTypes = RoleType::where('role_id', $roleId)->whereNull('deleted_at')->get();
            return response()->json(['role_types' => $roleTypes]);
        } catch (Exception $exception) {
            Log::error($exception);
            return sendJsonErrMsg('Unable to fetch role types.');
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return mixed
     */
    public function saveRoleTypes(Request $request)
    {
        try {
            $validated = $this->validateRequest($request);
            DB::beginTransaction();
            try {
                $roleTypes = $validated['roleTypes'];
                $roleId = $request->get('roleId');
                if (!$roleId) {
                    return response()->json(['error' => 'Role ID is required'], 400);
                }
                foreach ($roleTypes as $roleType) {
                    // Check if the role type already exists
                    if ($roleType['id'] > 0) {
                        $existingRoleType = RoleType::find($roleType['id']);
                        if ($existingRoleType) {
                            // Update existing role type
                            $existingRoleType->update([
                                'name' => Str::slug($roleType['name']),
                                'display_name' => $roleType['name'],
                                'tip_distribution_percentage' => $roleType['percentage'],
                                'updated_at' => now(),
                            ]);
                            continue;
                        }
                    } else {
                        $roleType = DB::table('role_types')->insertGetId([
                            'role_id' => $roleId,
                            'name' => Str::slug($roleType['name']),
                            'display_name' => $roleType['name'],
                            'tip_distribution_percentage' => $roleType['percentage'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
                DB::commit();

                return response()->json([]);
            } catch (Exception $e) {
                DB::rollBack();
                Log::error('Error saving role types: ' . $e->getMessage(), ['exception' => $e]);
                return response()->json(['error' => 'Failed to save gratuity setting'], 500);
            }
        } catch (Exception $exception) {
            Log::error($exception);
            return sendJsonErrMsg('Unable to add sub role.');
        }
    }
    /**
     * Delete the role type
     *
     * @param int $id
     */
    public function deleteRoleType(int $id)
    {
        try {
            DB::table('role_types')
                ->where('id', $id)
                ->update([
                    'deleted_at' => mysqlDT(),
                    'deleted_by' => Auth::id()
                ]);

            return response()->json([]);
        } catch (Exception $exception) {
            Log::error($exception);
            return sendJsonErrMsg("Unable to delete role type.");
        }
    }
    /**
     * Validate the request for creating gratuity setting.
     *
     * @param Request $request
     * @return array
     * @throws Exception
     */
    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'roleTypes' => 'required|array',
            'roleTypes.*.percentage' => 'required|numeric|min:0|max:100',
            'roleTypes.*.name' => 'required|string'
        ]);
    }
}
