<?php

namespace App\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageFormResource extends JsonResource
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
            "id" => $data->id ?? null,
            "form_package_id" => $data->form_package_id ?? null,
            "status" => $data->status ?? null,
            "form_name" => $data->form_name ?? null,
            "description" => $data->description ?? null,
            "fields" => !empty($data->form_fields) ? json_decode($data->form_fields, true) : null,
        ];
    }
}
