<?php

namespace App\Mail\TourBooking;

use App\Models\EmailTemplate;
use App\Traits\TemplateModification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PosOrderReceipt extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, TemplateModification;

    /**
     * The order data.
     *
     * @var array
     */
    public $order;

    /**
     * The order data.
     *
     * @var array
     */
    public $booking;

    /**
     * The email template.
     *
     * @var object
     */
    public $template;

    /**
     * PDF path
     *
     * @var bool
     */
    public $pdf;

    /**
     * Create a new message instance.
     *
     * @param  array  $order
     * @param  string  $template_alias
     * @param  object  $attach_pdf
     */
    public function __construct($order = [], $template = null)
    {
        $this->order = $order;
        $this->booking = (array)$order['comboBookings'][0]['bookingM'];
        $disk = Storage::disk('s3');
        $fileName =  "Order-" . $order['orderId'] . ".pdf";
        if ($disk->exists('pdf/' . $order['tenantId'] . '/' . $fileName)) {
            $this->pdf = 'pdf/' .  $order['tenantId'] . '/' . $fileName;
        } else {
            $pdf = PDF::loadView('users.common.pos-bookings-email-receipt', $order);
            $content = $pdf->download()->getOriginalContent();
            Storage::disk('s3')->put('pdf/' .  $order['tenantId'] . '/' . $fileName, $content);
            $this->pdf = 'pdf/' .  $order['tenantId'] . '/'  . $fileName;
        }
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        if (isset($this->booking['replyTo']))
            $this->replyTo($this->booking['replyTo']['email'], $this->booking['replyTo']['name']);

        $order = $this->order;
        $booking = (array)$order['comboBookings'][0]['bookingM'];
        $this->to($this->booking['custEmail'], $this->booking['custName'])
            ->subject('POS Order Receipt')
            ->view('mail.pos-order-receipt', ['order' => $order, 'booking' => $booking]);

        if ($this->pdf != '') {
            $pdfInvoiceFile = Storage::disk('s3')->url($this->pdf);
            $this->attach($pdfInvoiceFile, [
                'as' => "Order-" . $this->order['orderId'] . '.pdf',
                'mime' => 'application/pdf',
            ]);
        }


        $this->withSymfonyMessage(function ($message) {
            $message->getHeaders()->addTextHeader(
                'X-Model-ID',
                isset($this->order['orderId']) ? $this->order['orderId'] : 0
            );
            $message->getHeaders()->addTextHeader(
                'X-Model-TP',
                isset($this->order['emailType']) ? $this->order['emailType'] : 0
            );
            $message->getHeaders()->addTextHeader(
                'X-Model-OB',
                isset($this->order['operationBy']) ? $this->order['operationBy'] : 1
            );
            $message->getHeaders()->addTextHeader(
                'X-Model-UT',
                isset($this->order['operationByUserType']) ? $this->order['operationByUserType'] : 'Admin'
            );
            $message->getHeaders()->addTextHeader(
                'X-Model-TID',
                isset($this->order['tenantId']) ? $this->order['tenantId'] : ''
            );
        });

        return $this;
    }

    /**
     * Get the array tags of the notification.
     *
     * @return array
     */
    public function getTags(): array
    {
        return [];
    }

    /**
     * Get the array tag values of the notification.
     *
     * @return array
     */
    public function getTagsReplacement(): array
    {
        return [];
    }
}
