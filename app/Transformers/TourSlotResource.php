<?php

namespace App\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TourSlotResource extends JsonResource
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
            'is_affiliate' => $this['is_affiliate'],
            'applyOnSlots' => $this['applyOnSlots'],
            'recordsHistory' =>!empty($this['recordsHistory']) ? $this['recordsHistory'] : [],
            'slotTimes' => $this['slotTimes'],
            'tourOperatorId' => $this['tourOperatorId'],
            'rateGroups' => !empty($this['rateGroups']) ? $this['rateGroups'] : [],
			'stripe_destination_id'=> $this['stripe_destination_id'] ?? '',
			'overbook_seats'=> $this['overbook_seats'] ?? '',
            'hotels' => $this['hotels'],
			'isGroupRateEnabled' => $this['isGroupRateEnabled'] ?? 0,
			'paxWiseGroupRateIds' => $this['paxWiseGroupRateIds'] ?? [],
			'paxWiseGroupRates' => $this['paxWiseGroupRates'] ?? [],
            'paxWiseGroupAdditionalCharges' => $this['paxWiseGroupAdditionalCharges'] ?? [],
        ];
    }
}
