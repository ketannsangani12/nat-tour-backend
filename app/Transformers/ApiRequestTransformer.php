<?php 
namespace App\Transformers;

class ApiRequestTransformer
{
    /**
     * Transform request data by converting comma-separated strings into arrays.
     *
     * @param object $request The incoming request object.
     * @return void
     */
    public static function transformRequestData($request): void
    {
        // Define the fields to transform
        $fields = ['permit_fee', 'rate_group', 'tourists'];

        // Loop through each field and convert comma-separated strings into arrays
        foreach ($fields as $field) {
            if (isset($request->{$field}[0])) {
                $request->{$field} = explode(",", $request->{$field}[0]);
            }
        }
    }
}
