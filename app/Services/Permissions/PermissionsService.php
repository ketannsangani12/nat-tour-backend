<?php

namespace App\Services\Permissions;

use Illuminate\Support\Facades\DB;
use App\Models\Permission;
use Illuminate\Support\Facades\Auth;



class PermissionsService
{
	/**
	 * Get list of permissions category wise
	 *
	 * @param int $tourOperatorId
	 *
	 * @return mixed
	 */

	public static function getPermissionsList($permissions, $permissionsArray): mixed
	{
	    $categoryMappings = [
	        'Menu' => [],
	        'Sub Menu' => [],
	        'Profile' => [],
	        'Action' => [],
			'Payment Methods' => [],
			'Cancellation Refund Methods' => [],
	        'Tour Package' => [],
	        'POS' => [],
	        'Tour Slots Bulk Manager' => [],
	        'Board' => [],
	        'Tips Center' => [],
	        'Report' => [],
	        'Ticketing App' => []
	    ];

	    // Loop through permissions and categorize them
	    foreach ($permissions as $permission) {
	        $checked = in_array($permission->id, $permissionsArray) ? 'checked=checked' : '';

	        // Check if category is 'Report' and apply special conditions for 'affiliate'
	        if ($permission->category == "Report" && $permission->guard_name == "affiliate") {
	            if (in_array($permission->display_name, [
	                'Affiliate Booked Report',
	                'Affiliate Comprehensive Report',
	                'Affiliate Seats Report'
	            ])) {
	                $categoryMappings['Report'][] = [
	                    'id' => $permission->id,
	                    'display_name' => $permission->display_name,
	                    'checked' => $checked
	                ];
	            }
	        } else if (isset($categoryMappings[$permission->category]) && 
				!in_array($permission->display_name, [
					'TripAdvisor Booking Report',
					'GetYourGuide Booking Report'
				])
			) {
	            $categoryMappings[$permission->category][] = [
	                'id' => $permission->id,
	                'display_name' => $permission->display_name,
	                'checked' => $checked
	            ];
	        }
	    }

	    // Generate HTML for each category
	    $html = '';
	    foreach ($categoryMappings as $category => $permissionsList) {
	        if (!empty($permissionsList)) {
	            $htmlContent = htmlPermissionList($permissionsList);
	            $html .= htmlAccordion($htmlContent, $category, strtolower(str_replace(' ', '_', $category)));
	        }
	    }

	    return $html;
	}


	/**
	 * Get the access level
	 *
	 * @param $request
	 * @param $guard_name
	 * @param $model_type
	 *
	 * @return mixed
	 */
	public static function accessLevel($request, $guardName, $modelType)
	{
		$html = '';

	    if ($request->userId) {
	        $permissionsQuery = Permission::where('guard_name', $guardName)->orderBy('display_name');

	        // Retrieve permissions based on user login
            $permissionsArray = DB::table('model_has_permissions as mhp')
                ->join('permissions as p', 'p.id', '=', 'mhp.permission_id')
                ->where('mhp.model_id', Auth::id())
                ->where('mhp.model_type', $modelType)
                ->pluck('permission_id');
                
	        $permissions = $permissionsQuery->whereIn('id', $permissionsArray)->get(['id', 'display_name', 'category', 'guard_name']);

	        // Get assigned permissions
	        $permissionsAssigned = DB::table('model_has_permissions')
	            ->where('model_id', $request->userId)
	            ->where('model_type', $modelType)
	            ->pluck('permission_id')
	            ->toArray(); // Directly collect permission ids as an array

	        // Permissions to display
	        if (!empty($permissionsAssigned)) {
	            $html = PermissionsService::getPermissionsList($permissions, $permissionsAssigned);
	        }
		}

		return $html;
	}

}
