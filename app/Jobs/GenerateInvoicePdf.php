<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;
use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Invoice;
use App\Services\Invoice\InvoiceDataService;
use App\Services\Invoice\InvoicePdfService;

class GenerateInvoicePdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * Bounded so a hung DomPDF render cannot pin a queue worker forever.
     *
     * @var int
     */
    public $timeout = 300;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The seconds to wait before retrying a failed attempt.
     *
     * @var int
     */
    public $backoff = 10;

    /**
     * The invoice UUID.
     *
     * @var string
     */
    public $invoiceUuid;

    /**
     * Create a new job instance.
     *
     * @param  string  $invoiceUuid
     * @return void
     */
    public function __construct(string $invoiceUuid)
    {
        $this->invoiceUuid = $invoiceUuid;
    }

    /**
     * Prevent two generations of the same invoice PDF running at once.
     *
     * A user clicking "Generate PDF" repeatedly would otherwise queue several
     * identical heavy DomPDF renders; only the first needs to run.
     *
     * @return array
     */
    public function middleware()
    {
        return [
            (new WithoutOverlapping($this->invoiceUuid))
                ->dontRelease()
                ->expireAfter($this->timeout),
        ];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $invoice = Invoice::uuid($this->invoiceUuid)
            ->with(['tourOperator', 'affiliate'])
            ->first();

        if (!$invoice) {
            Log::warning('GenerateInvoicePdf: Invoice not found for UUID ' . $this->invoiceUuid);
            return;
        }

        $bookings = InvoiceDataService::bookings($invoice);
        $vars = InvoiceDataService::invoiceStub($bookings);

        $vars['invoice'] = $invoice;
        $vars['page_title'] = 'Show Invoice';
        $vars['pdf'] = true;

        $pdf = PDF::loadView('users.admin.invoice.print1', $vars);
        $content = $pdf->download()->getOriginalContent();

        Storage::disk(InvoicePdfService::DISK)->put(
            InvoicePdfService::pathForInvoice($invoice),
            $content
        );
    }

    /**
     * Handle a job failure.
     *
     * Lets the failure surface to the queue's failed_jobs table (for retry /
     * alerting) instead of being swallowed by a broad catch.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(Throwable $exception)
    {
        Log::error('GenerateInvoicePdf failed for UUID ' . $this->invoiceUuid . ': ' . $exception->getMessage());
    }
}
