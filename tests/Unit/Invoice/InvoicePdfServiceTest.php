<?php

namespace Tests\Unit\Invoice;

use Tests\TestCase;
use App\Services\Invoice\InvoicePdfService;
use Illuminate\Support\Facades\Storage;
use Mockery;

/**
 * Unit tests for the invoice PDF status/path helper that backs the
 * "Generate PDF" polling flow. Fully mocked - the parent TestCase fakes the
 * 's3' disk, so no real network or database access is needed.
 */
class InvoicePdfServiceTest extends TestCase
{
    // No tenant DB needed; we pass the tenant id explicitly.
    protected $tenancy = false;

    /** @test */
    public function it_builds_a_forward_slash_s3_key()
    {
        $this->assertSame(
            'pdf/dixies/INV-1001.pdf',
            InvoicePdfService::path('dixies', 'INV-1001')
        );
    }

    /** @test */
    public function status_reports_not_ready_when_the_pdf_is_absent()
    {
        // Storage::fake('s3') is applied in the parent setUp, so the disk is empty.
        $status = InvoicePdfService::status('dixies', 'INV-2002');

        $this->assertFalse($status['ready']);
        $this->assertNull($status['url']);
        $this->assertNull($status['last_modified']);
    }

    /** @test */
    public function status_reports_ready_with_a_url_once_the_pdf_exists()
    {
        Storage::disk(InvoicePdfService::DISK)->put(
            InvoicePdfService::path('dixies', 'INV-3003'),
            '%PDF-1.4 fake content'
        );

        $status = InvoicePdfService::status('dixies', 'INV-3003');

        $this->assertTrue($status['ready']);
        $this->assertNotNull($status['url']);
        $this->assertIsInt($status['last_modified']);
    }

    /** @test */
    public function status_reports_not_ready_when_the_existence_check_throws()
    {
        // Simulate S3 throwing (e.g. a 403 HeadObject when the bucket policy
        // lacks s3:ListBucket) instead of returning false. The service must
        // swallow it and report "not ready" rather than bubbling a 500.
        $disk = Mockery::mock();
        $disk->shouldReceive('exists')
            ->andThrow(new \RuntimeException('Unable to check existence'));

        Storage::shouldReceive('disk')
            ->with(InvoicePdfService::DISK)
            ->andReturn($disk);

        $status = InvoicePdfService::status('dixies', 'INV-5005');

        $this->assertFalse($status['ready']);
        $this->assertNull($status['url']);
        $this->assertNull($status['last_modified']);
    }

    /** @test */
    public function status_is_scoped_per_tenant()
    {
        Storage::disk(InvoicePdfService::DISK)->put(
            InvoicePdfService::path('dixies', 'INV-4004'),
            'content'
        );

        // Same invoice number, different tenant, must not be found.
        $this->assertTrue(InvoicePdfService::status('dixies', 'INV-4004')['ready']);
        $this->assertFalse(InvoicePdfService::status('mahikan', 'INV-4004')['ready']);
    }
}
