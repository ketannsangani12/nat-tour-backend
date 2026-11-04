<?php

namespace App\Jobs;

use App\Mail\TourBooking\NewsLetter;
use App\Models\TourPackage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class NewsLetterEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;

    protected $customers;
    protected $userId;
    protected $userType;

    /**
     * Create a new job instance.
     */
    public function __construct($customers, $userId, $userType)
    {
        $this->customers = $customers;
        $this->userId    = $userId;
        $this->userType  = $userType;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $emailType = config('constants.email_types.news_letter');
        $tenantId  = tenant()->id;

        $package = TourPackage::first();

        foreach ($this->customers as $customer) {
            $emailData = [
                'custEmail'            => $customer->email,
                'custName'             => $customer->name,
                'tourPackageId'        => $package->id,
                'packageFromEmail'     => $package->from_email,
                'packageFromName'      => $package->from_name,
                'emailType'            => $emailType,
                'operationBy'          => $this->userId,
                'operationByUserType'  => $this->userType,
                'tenantId'             => $tenantId,
            ];

            Mail::send(new NewsLetter($emailData, 'news_letter'));
        }
    }
}
