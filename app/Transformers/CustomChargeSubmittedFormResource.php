<?php

namespace App\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomChargeSubmittedFormResource extends JsonResource
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
        $results = [];
        if (isset($data->resource) && !empty($data->resource)) {
            $data->each(function ($item) use (&$results) {
                $results[] = [
                    "tour_booking_id" => $item->tour_booking_id ?? null,
                    "custom_charge_id" => $item->custom_charge_id ?? null,
                    "parent_id" => $item->parent_id ?? null,
                    "amount" => $item->amount ?? null,
                    "fees" => $item->fees ?? null,
                    "taxable" => $item->taxable ?? null,
                    "transaction_id" => $item->transaction_id ?? null,
                    "description" => $item->description ?? null,
                    "payment_method" => $item->payment_method ?? null,
                    "status" => $item->status ?? null
                ];
            });
        }
        return $results;
    }
}
