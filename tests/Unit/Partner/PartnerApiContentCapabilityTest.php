<?php

namespace Tests\Unit\Partner;

use App\Repositories\PartnerAPI\PartnerApiRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Verifies the partner-api honours the OCTO "content" capability end to end.
 *
 * When the reseller sends `Octo-Capabilities: octo/pricing,octo/content`, setupOctoContext
 * parses + merges it into the request (`octo_capabilities`), and the reused OCTO code
 * (`OctoService::hasCapability('content')`, which reads `request()->octo_capabilities`)
 * adds product content: title, shortDescription/description, features, locations, and media.
 *
 * Media (images) are pulled from the tenant `tour_package_photos` table (column `path`)
 * and served from S3 at `images/tour-operator/{tenantId}/package/{packageId}/{path}`
 * (OctoService::buildPackageMedia -> Storage::disk('s3')->url()).
 *
 * Run with:
 *   docker exec -it nat-app php artisan test tests/Unit/Partner/PartnerApiContentCapabilityTest.php
 */
class PartnerApiContentCapabilityTest extends TestCase
{
    private const RESELLER = 'partner-content-reseller';
    private const API_KEY  = 'partner-content-key';

    protected array $cleanupCustomerIds = [];
    protected array $cleanupPackageIds  = [];
    protected array $cleanupPhotoIds    = [];

    protected function tearDown(): void
    {
        if (!empty($this->cleanupPhotoIds)) {
            DB::connection('tenant')->table('tour_package_photos')->whereIn('id', $this->cleanupPhotoIds)->delete();
        }
        if (!empty($this->cleanupPackageIds)) {
            DB::connection('tenant')->table('package_affiliate_assignments')->whereIn('package_id', $this->cleanupPackageIds)->delete();
            DB::connection('tenant')->table('tour_packages')->whereIn('id', $this->cleanupPackageIds)->delete();
        }
        if (!empty($this->cleanupCustomerIds)) {
            DB::connection('tenant')->table('customers')->whereIn('id', $this->cleanupCustomerIds)->delete();
        }
        parent::tearDown();
    }

    private function createAffiliate(): int
    {
        $operatorId = DB::connection('tenant')->table('tour_operators')->value('id');
        $id = DB::connection('tenant')->table('customers')->insertGetId([
            'tour_operator_id' => $operatorId,
            'name'             => self::RESELLER,
            'email'            => 'content-' . uniqid() . '@example.com',
            'phone_number'     => '0000000000',
            'is_affiliate'     => 'Yes',
            'is_ota'           => 1,
            'ota_id'           => self::RESELLER,
            'reseller_api_key' => self::API_KEY,
            'status'           => 'Active',
            'created_at'       => Carbon::now(),
            'updated_at'       => Carbon::now(),
        ]);
        $this->cleanupCustomerIds[] = $id;
        return $id;
    }

    private function createPackageWithPhoto(int $affiliateId): array
    {
        $operatorId = DB::connection('tenant')->table('tour_operators')->value('id');
        $packageId = DB::connection('tenant')->table('tour_packages')->insertGetId([
            'tour_operator_id'     => $operatorId,
            'name'                 => 'Content Test Package ' . uniqid(),
            'short_description'    => 'A short blurb for the partner content test.',
            'long_description'     => 'A longer description used to verify the content capability.',
            'status'               => 'Active',
            'type'                 => 'Regular',
            'created_by'           => 1,
            'created_by_user_type' => 'Admin',
            'created_at'           => Carbon::now(),
            'updated_at'           => Carbon::now(),
        ]);
        $this->cleanupPackageIds[] = $packageId;

        DB::connection('tenant')->table('package_affiliate_assignments')->insert([
            'affiliate_id' => $affiliateId,
            'package_id'   => $packageId,
            'created_at'   => Carbon::now(),
            'updated_at'   => Carbon::now(),
        ]);

        $photoId = DB::connection('tenant')->table('tour_package_photos')->insertGetId([
            'tour_package_id' => $packageId,
            'path'            => 'partner-content-photo.png',
            'placement'       => 0,
            'status'          => 'Active',
            'deleted_by'      => 0,
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ]);
        $this->cleanupPhotoIds[] = $photoId;

        return ['packageId' => $packageId, 'path' => 'partner-content-photo.png'];
    }

    private function bindRequest(int $affiliateId, string $capabilities): Request
    {
        $request = Request::create('/api/v1/partner-api/products', 'GET');
        $request->headers->set('Octo-Capabilities', $capabilities);
        $request['data'] = ['AffiliateId' => $affiliateId];
        $this->app->instance('request', $request);
        return $request;
    }

    private function findProduct(array $listData, int $packageId): ?array
    {
        foreach ($listData as $product) {
            if ((string) ($product['id'] ?? '') === (string) $packageId) {
                return $product;
            }
        }
        return null;
    }

    public function test_content_capability_returns_content_and_media(): void
    {
        $affiliateId = $this->createAffiliate();
        $pkg         = $this->createPackageWithPhoto($affiliateId);

        $request  = $this->bindRequest($affiliateId, 'octo/pricing,octo/content');
        $response = app(PartnerApiRepository::class)->getProductList($request);

        $this->assertSame(200, $response->getStatusCode());
        $product = $this->findProduct($response->getData(true), $pkg['packageId']);
        $this->assertNotNull($product, 'The content-test package must be listed.');

        // Content fields are present.
        $this->assertArrayHasKey('title', $product);
        $this->assertArrayHasKey('description', $product);
        $this->assertArrayHasKey('media', $product, 'Content capability must add a media array.');
        $this->assertNotEmpty($product['media'], 'Media must contain the package photo.');

        // Media points at the S3 package-photo path.
        $src = $product['media'][0]['src'] ?? '';
        $this->assertStringContainsString('/package/' . $pkg['packageId'] . '/' . $pkg['path'], $src,
            "Image src must resolve to the tour_package_photos path on S3, got '{$src}'.");
    }

    public function test_without_content_capability_no_media_is_returned(): void
    {
        $affiliateId = $this->createAffiliate();
        $pkg         = $this->createPackageWithPhoto($affiliateId);

        // Only pricing requested — content must NOT be included.
        $request  = $this->bindRequest($affiliateId, 'octo/pricing');
        $response = app(PartnerApiRepository::class)->getProductList($request);

        $this->assertSame(200, $response->getStatusCode());
        $product = $this->findProduct($response->getData(true), $pkg['packageId']);
        $this->assertNotNull($product, 'The package must still be listed without the content capability.');

        $this->assertArrayNotHasKey('media', $product, 'No media should be returned when content is not requested.');
        $this->assertArrayNotHasKey('description', $product, 'No description should be returned when content is not requested.');
    }
}
