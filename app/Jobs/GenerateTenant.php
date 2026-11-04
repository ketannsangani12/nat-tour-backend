<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateTenant implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 0;

    /**
     * The data array.
     *
     * @var $data
     */
    public $data;

    /**
     * Create a new job instance.
     *
     * @param  array $data
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $subdomain = $this->data['subdomain'];
            $name = $this->data['name'];
            $timezone = $this->data['timezone'];

            // Validate timezone
            if (!in_array($timezone, \DateTimeZone::listIdentifiers())) {
                throw new Exception("Invalid timezone: {$timezone}");
            }

            // Log input data
            Log::info("Creating tenant with subdomain: {$subdomain}, name: {$name}, timezone: {$timezone}");

            // Create tenant
            $tenant = Tenant::create([
                'id' => $subdomain
            ]);

            // Update tenant data
            DB::table('tenants')
                ->where('id', $subdomain)
                ->update([
                    'name' => $name,
                    'timezone' => $timezone,
                ]);

            // Create domain
            $tenant->domains()->create([
                'domain' => $subdomain . config('constants.environment.' . config('app.env')),
            ]);

            Log::info("Tenant created successfully: " . json_encode($tenant->toArray()));
        } catch (Exception $exception) {
            Log::error("Failed to create tenant: {$exception->getMessage()}");
            throw $exception; // Re-throw to mark job as failed
        }
    }
}
