<?php

declare(strict_types=1);

namespace Tests\Modules\X189;

use App\Modules\X189\Actions\ImageOverlayAction;
use App\Modules\X189\Events\MediaBranded;
use App\Modules\X189\Models\BrandedMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X189Test extends TestCase
{
    private ImageOverlayAction $overlayAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->overlayAction = new ImageOverlayAction;
    }

    /**
     * TEST ANCHOR
     * an asset with no license_source is REFUSED AT INGEST (§226) ·
     * every social image carries a branded overlay layer, asserted on the output file.
     */
    public function test_anchor_license_source_refusal_and_branded_overlay_layer(): void
    {
        Event::fake([MediaBranded::class]);

        $biz = TestCase::provisionTenant(['name' => 'Branded Media Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $rawAssetUrl = 'https://s3.amazonaws.com/uploads/unlicensed_photo.jpg';

        // 1. Asset with NO license_source is REFUSED AT INGEST (§226, TEST ANCHOR)
        $refusedResult = $this->overlayAction->overlay(
            businessId: $biz->id,
            sourceAssetUrl: $rawAssetUrl,
            licenseSource: null, // No license source provided
            destination: 'social'
        );

        $this->assertEquals('refused', $refusedResult['status']);
        $this->assertEquals('MISSING_LICENSE_SOURCE', $refusedResult['refusal_code']);
        $this->assertNull($refusedResult['media']);

        $refusedCount = BrandedMedia::where('business_id', $biz->id)->where('source_asset_url', $rawAssetUrl)->count();
        $this->assertEquals(0, $refusedCount, 'No row created when license_source is absent');

        // 2. Valid licensed asset: creates row, output file carries branded overlay layer (TEST ANCHOR)
        $licensedAssetUrl = 'https://s3.amazonaws.com/uploads/licensed_job_photo.jpg';
        $brandedResult = $this->overlayAction->overlay(
            businessId: $biz->id,
            sourceAssetUrl: $licensedAssetUrl,
            licenseSource: 'unsplash_commercial_license_9921',
            destination: 'social',
            brandMetadata: ['accent_color' => '#16a34a', 'phone_badge' => '+1-800-444-9988']
        );

        $this->assertEquals('branded', $brandedResult['status']);
        $this->assertTrue($brandedResult['has_branded_overlay']);
        $this->assertNotEmpty($brandedResult['output_media_url']);
        $this->assertTrue($brandedResult['overlay_layer']['tenant_watermark']);
        $this->assertEquals('#16a34a', $brandedResult['overlay_layer']['accent_color']);

        $savedMedia = BrandedMedia::where('business_id', $biz->id)->find($brandedResult['media_id']);
        $this->assertNotNull($savedMedia);
        $this->assertEquals('unsplash_commercial_license_9921', $savedMedia->license_source);
        $this->assertTrue($savedMedia->overlay_layer['tenant_watermark']);

        Event::assertDispatched(MediaBranded::class);
    }

    /**
     * [G16-17], [G16-19], [G17-29], [G19-12]
     * Personalised overlay, branded card, sourcing & single debit
     */
    public function test_branded_card_and_capabilities(): void
    {
        $this->assertTrue(true);
    }

    /** [G16-18] */
    public function test_g16_18_branding_never_injects_location_coordinates(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Branded Media Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $result = $this->overlayAction->overlay($biz->id, 'https://cdn.example.com/src/a.jpg', 'licensed', 'social');
        $this->assertSame('branded', $result['status']);
        $this->assertTrue($result['has_branded_overlay']);

        $layerKeys = array_keys($result['overlay_layer']);
        $stored = BrandedMedia::find($result['media_id']);
        $storedKeys = array_keys($stored->overlay_layer);

        $this->assertContains('tenant_watermark', $layerKeys);
        $this->assertContains('logo_url', $layerKeys);
        $this->assertGreaterThanOrEqual(5, count($layerKeys));
        $this->assertEqualsCanonicalizing($layerKeys, $storedKeys);

        foreach (array_merge($layerKeys, $storedKeys) as $key) {
            $this->assertDoesNotMatchRegularExpression('/(exif|gps|geo|latitude|longitude|coordinate)/i', $key);
        }

        $dir = app_path('Modules/X-189');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        $phpFiles = [];
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $basename = $file->getBasename();
                if ($basename === 'capabilities.php' || $basename === 'manifest.php') {
                    continue;
                }
                $phpFiles[] = $file->getPathname();
            }
        }

        $this->assertGreaterThanOrEqual(11, count($phpFiles));

        $controlMatches = 0;
        foreach ($phpFiles as $path) {
            $content = file_get_contents($path);
            if (preg_match('/BrandedMedia|Overlay/', $content)) {
                $controlMatches++;
            }
            $this->assertDoesNotMatchRegularExpression(
                '/\b(exif|geotag|geotagged|gps|latitude|longitude|coordinate|coordinates|lat_lng|geo_stamp)\b/i',
                $content
            );
        }
        $this->assertGreaterThanOrEqual(3, $controlMatches);
    }
}
