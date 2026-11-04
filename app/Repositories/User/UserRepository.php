<?php

namespace App\Repositories\User;

use App\Models\Staff;
use App\Repositories\Repository;

class UserRepository extends Repository
{
    /**
     * @var string
     */
    public $model = Staff::class;

    /**
     * Get the profile data after user authenticate.
     *
     * @return object
     */
    public function getProfile(): object
    {
        return $this->model::select('id','tour_operator_id','first_name','last_name','email','phone_number','status','remember_token')->find(auth()->id());
    }
}
