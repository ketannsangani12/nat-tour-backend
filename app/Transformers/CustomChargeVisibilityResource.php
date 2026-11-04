<?php

namespace App\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomChargeVisibilityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        $data = $this ?? null;
        return [
            'is_custom_charge' => $data['is_custom_charge'] ? 1 : 0,
            'custom_charge_fees' => NULL,
            'custom_charge_data' =>
            [
                'custom_form_id' => NULL,
                'custom_data' =>
                [
                    'custom_charge_amount' => NULL,
                    'taxable' => NULL,
                    'comment' => NULL
                ],
                'tour_package_id' => NULL
            ]
        ];
    }
}
