<?php

namespace App\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddonSubmittedFormResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            "tour_package_form_id" => $this['form_id'] ?? null,
            "tour_booking_id" => $this['booking_id'] ?? null,
            "description" => $this['description'] ?? null,
            "fields" => $this['fields'] ?? null,
            "has_old_addons" => $this['has_old_addons'] ?? false,
            "has_new_addons" => $this['has_new_addons'] ?? false,
            "new_addons" => $this['new_addons'] ?? null,
        ];
    }
}
