<?php

namespace App\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Crypt;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request): array
    {

        $profilePhoto = '';
        if (!empty($this->profile_photo_url)) {
            $profilePhoto = profilePhoto(tenant()->id, $this->profile_photo_url);
        }
        return [
            'id' => $this->id,
            'tour_operator_id' => $this->tour_operator_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'status' => $this->status,
            'remember_token' => $this->remember_token,
            'profile_photo_url' => $profilePhoto,
            'tip_url' => route('customer.pay-tip', Crypt::encrypt($this->id))
        ];
    }
}
