<?php

namespace App\Services\Invoice;

use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;

/**
 * Centralises where the generated invoice PDF lives on S3 and how its
 * generation status is reported. Keeping the storage key in one place keeps
 * the generating job, the mailer that attaches it and the polling status
 * endpoint in sync.
 */
class InvoicePdfService
{
    /**
     * Disk the invoice PDFs are stored on.
     */
    public const DISK = 's3';

    /**
     * Build the canonical S3 key for an invoice PDF.
     *
     * Always uses a forward slash - this is an S3 object key, not a local
     * filesystem path, so DIRECTORY_SEPARATOR must not be used here.
     *
     * @param  string  $tenantId
     * @param  string  $invoiceNumber
     * @return string
     */
    public static function path(string $tenantId, string $invoiceNumber): string
    {
        return 'pdf/' . $tenantId . '/' . $invoiceNumber . '.pdf';
    }

    /**
     * Build the S3 key for a given invoice using the current tenant.
     *
     * @param  \App\Models\Invoice  $invoice
     * @return string
     */
    public static function pathForInvoice(Invoice $invoice): string
    {
        return self::path(tenancy()->tenant->id, $invoice->invoice_number);
    }

    /**
     * Report whether the PDF exists yet and, if so, where to download it.
     *
     * The client polls this after kicking off generation. `last_modified`
     * lets the client tell a freshly generated PDF apart from a stale one
     * left over from a previous run.
     *
     * @param  string  $tenantId
     * @param  string  $invoiceNumber
     * @return array{ready: bool, url: string|null, last_modified: int|null}
     */
    public static function status(string $tenantId, string $invoiceNumber): array
    {
        $disk = Storage::disk(self::DISK);
        $path = self::path($tenantId, $invoiceNumber);

        $notReady = [
            'ready' => false,
            'url' => null,
            'last_modified' => null,
        ];

        try {
            // exists() can throw instead of returning false (e.g. a 403
            // HeadObject when the bucket policy lacks s3:ListBucket). Since a
            // HEAD on an object that DOES exist still returns 200, treating a
            // failed check as "not ready yet" keeps polling correct: it will
            // report ready as soon as the PDF is actually written.
            if (!$disk->exists($path)) {
                return $notReady;
            }

            return [
                'ready' => true,
                'url' => $disk->url($path),
                'last_modified' => $disk->lastModified($path),
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Invoice PDF status check failed for "' . $path . '": ' . $e->getMessage());
            return $notReady;
        }
    }
}
