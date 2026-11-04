<?php
namespace Tests\Utils;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Staff;
use Closure;

class TestHelpers{


    /** turn a json response into std-object 
     * 
    */
    public static function jsonBody(string $jsonStr){
        return json_decode(trim(explode("GMT", $jsonStr)[1]));
    }
    /**
     * to auth as an admin user, you can use this helper: you have to pass
     * $this->actingAs from the unittest base class
     */
    public static function AuthTestUser(Closure $as, $guard="admin"){
        $admin = Admin::factory()->create()->first();
        $as($admin, $guard);
        return $admin;
    }

    /**
    * to auth as a staff user, you can use this helper: you have to pass
    * $this->actingAs from the unittest base class
    */
    // public static function AuthAsTestStaff(Closure $as, $guard="staff"){
    //     $staff = Staff::factory()->create([
    //         'role' => 'Tour Operator'
    //     ])->first();
    //     $as($staff, $guard);
    //     return $staff;
    // }

    // /**
    // * to auth as an affiliate user, you can use this helper: you have to pass
    // * $this->actingAs from the unittest base class
    // */
    // public static function AuthAsTestAffiliate(Closure $as, $guard="customer"){
    //     $customer = Customer::factory()->create([
    //         'role' => 'Affiliate',
    //         'is_affiliate' => 'Y'
    //     ])->first();
    //     $as($customer, $guard);
    //     return $customer;
    // }

}

