<?php

namespace App\Jobs;
use Exception;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;


class StripeInvoiceUpdateStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Stripe Webhook invoice object.
     * @var 
     */
    public $stripeInvoice;

    /**
     * Stripe Webhook invoice event type: invioce.paid, invoice.deleted etc..
     * @var
     */ 
    public $eventType;

    /**
     * log message for un handeled webhook events
     * @var
     */ 
    public $noEventTypeMsg = "Event type not handeled in invoice webhook handler";

    /**
     * Create a new job instance.
     *
     * @param  object  $stripeInvoice
     * @return void
     */
    public function __construct($stripeInvoice, $eventType)
    {
        $this->stripeInvoice = $stripeInvoice;
        $this->eventType = $eventType;
    }

    /**
     * Handle the invoice status update job.
     * @return void
     */
    public function handle() : void 
    {
        $data = $this->stripeInvoice;
        $type = $this->eventType;
        Log::debug("-------------Stripe Invoice Webhook --------------------");
        try{
            //Log::debug($data);
            DB::beginTransaction();
                $uuid = $data->metadata->invoice_id;
                $invoice = Invoice::uuid($uuid)->with(['tourOperator', 'affiliate'])->first();
                if ( $invoice ){
                    $invoice->update( ["external_status" => $data->status ] );
                    switch($type){
                        case 'invoice.deleted':
                            $this->onInvoiceDelete($invoice);
                        break;
                        case 'invoice.marked_uncollectible':
                            $this->onInvoiceOverdue($invoice);
                        break;
                        case 'invoice.paid':
                        case 'invoice.created':
                        case 'invoice.finalized':
                            Log::debug(" invoice action:  " . $type . " updated invoice status ");
                        break;   
                        default:
                            Log::debug($this->noEventTypeMsg);
                    }
                }
            DB::commit();

        } catch (Exception $exception) {
            Log::error($exception);
            DB::rollBack();
        }
        Log::debug("-------------Stripe Invoice Webhook --------------------");
    }

    /**
     * onInvoiceOverdue
     * on 
     * @param Invoice $invoice object found by stripe meta data uuid
     * @return void
     */
    public function onInvoiceOverdue(Invoice $invoice) : void {
        try{
            $invoice->update(['status'=>'overdue']);
        }catch(Exception $err){
            Log::error($err);
        }
        
    }

    /** 
     * onInvoiceDelete
     * if the linked invoice is not deleted then that means someone deleted the 
     * stripe invoice in the stripe console. In this case: update the active invoice 
     * and remove the link to the stripe invoice info 
     * @param Invoice $invoice object found by stripe meta data uuid
     * @return void
    */
    public function onInvoiceDelete(Invoice $invoice) : void {
        try{
            if ($invoice->deleted_by != 0) {
                $invoice->update(['external_payment_link' => '', 
                                    'external_invoice_id'=> '', 
                                    'external_customer_id'=> '']);
            }
        }catch(Exception $err){
            Log::error($err);
        }
    
    }

}// end class
