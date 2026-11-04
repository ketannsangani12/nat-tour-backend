<?php

namespace App\Mail;

use App\Constants\Constants;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;

class SendInvoiceToAffiliate extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The invoice instance.
     *
     * @var \App\Models\Invoice
     */
    public $invoice;

    /**
     * Create a new message instance.
     *
     * @param  \App\Models\Invoice  $invoice
     * @return void
     */
    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->from($this->invoice->tourOperator->email, $this->invoice->tourOperator->name);

        if ($this->invoice->email_sent == Constants::STATUS_ONE) {
            $this->subject('New invoice from ' . $this->invoice->tourOperator->name);
        } else {
            $this->subject('Invoice ' . $this->invoice->invoice_number . ' Due. Thank you!');
        }

        $this->view('users.admin.invoice.affiliate-email');

        $path = \App\Services\Invoice\InvoicePdfService::pathForInvoice($this->invoice);

        if (Storage::disk('s3')->exists($path)) {
            $pdfInvoiceFile = Storage::disk('s3')->url($path);
            $this->attach($pdfInvoiceFile, [
                'as' => $this->invoice->invoice_number . '.pdf',
                'mime' => 'application/pdf',
            ]);
        }

        $this->withSymfonyMessage(function ($message) {
            $message->getHeaders()->addTextHeader(
                'X-Model-UUID',
                $this->invoice->uuid
            );
        });

        return $this;
    }
}
