<?php

namespace App\Utils;

use Exception;
use Illuminate\Support\Facades\DB;

class AuthUtils
{
    /**
     * expand tenant data from nat cental db 
     */
    public static function tenantPublicInfo($tenant)
    {
        $tenantInfo = ["name" => "", "logo" => "", "email" => "", "phone_number" => "", "website" => "", "status" => ""];

        try {
            $db = $tenant->tenancy_db_name;
            $cols = array_keys($tenantInfo);
            $tenantData = DB::setDatabaseName($db)
                ->table("{$db}.tour_operators")
                ->select($cols)
                ->get()
                ->first();

            $tenantInfo = (array)$tenantData;
            $tenantInfo["logo_path"] =  env("AWS_URL") . "/images/tour-operator/" . $tenant->id . "/logo/" . $tenantData->logo;

            $settings = DB::setDatabaseName($db)
                ->table("{$db}.settings")
                ->select("value")
                ->where("key", "=", "BOOKING_ID_PREFIX")
                ->get()
                ->first();

            $tenantInfo["booking_prefix"] = "";

            if ($settings) {
                $tenantInfo["booking_prefix"] = $settings->value;
            }

            $domains = DB::select("SELECT tenant_id AS id, domain AS url, booking_prefix FROM domains;");
            $tenantInfo["tenants"] = $domains;
            $tenantInfo["error"] = null;
        } catch (Exception $err) {
            $tenantInfo["booking_prefix"] = "";
            $tenantInfo["tenants"] = [];
            $tenantInfo["error"] = $err;
        }

        return $tenantInfo;
    }
}
