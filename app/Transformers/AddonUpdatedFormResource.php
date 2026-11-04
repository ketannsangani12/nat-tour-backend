<?php

namespace App\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddonUpdatedFormResource extends JsonResource
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
            "fields" => is_string($this['fields']) ? json_decode($this['fields'], true) : $this['fields']
        ];
    }
}
