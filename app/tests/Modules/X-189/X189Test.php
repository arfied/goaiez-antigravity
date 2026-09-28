<?php

declare(strict_types=1);

namespace Tests\Modules\X189;

use App\Models\User;
use App\Modules\X189\Actions\BrandCardEnsureAction;
use App\Modules\X189\Actions\ImageOverlayAction;
use App\Modules\X189\Events\MediaBranded;
use App\Modules\X189\Models\BrandCard;
use App\Modules\X189\Models\BrandedMedia;
use App\Modules\X189\Ui\BrandCardEditor;
use App\Services\Sms\TenantNumbers;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class X189Test extends TestCase
{
    use RefreshesTenantDatabase;

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

        DB::table('fetch_sources')->insertOrIgnore([
            'key' => 'tenant_site',
            'method_ceiling' => 'light_fetch',
        ]);

        $canvas = imagecreatetruecolor(100, 100);
        ob_start();
        imagejpeg($canvas);
        $jpg = ob_get_clean();
        imagedestroy($canvas);
        Http::fake(['*' => Http::response($jpg, 200)]);

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

    /** [G16-18] */
    public function test_g16_18_branding_never_injects_location_coordinates(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Branded Media Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        DB::table('fetch_sources')->insertOrIgnore([
            'key' => 'tenant_site',
            'method_ceiling' => 'light_fetch',
        ]);

        $canvas = imagecreatetruecolor(100, 100);
        ob_start();
        imagejpeg($canvas);
        $jpg = ob_get_clean();
        imagedestroy($canvas);
        Http::fake(['*' => Http::response($jpg, 200)]);

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

    public function test_ensure_creates_card_with_tenant_number(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Card Tenant']);
        $ensure = app(BrandCardEnsureAction::class);
        $card = $ensure->handle($biz->id);
        $this->assertEquals(app(TenantNumbers::class)->ownBrandNumberFor($biz->id), $card->badge_text);

        $biz2 = TestCase::provisionTenant(['name' => 'Card Tenant 2']);
        $card2 = $ensure->handle($biz2->id);
        $this->assertNull($card2->badge_text);
    }

    public function test_editor_saves_validates_uploads(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => 'owner']);
        $biz = TestCase::provisionTenant(['name' => 'Editor Tenant', 'owner_user_id' => $owner->id]);
        DB::statement("SET app.business_id = '{$biz->id}'");

        Livewire::actingAs($owner);

        Livewire::test(BrandCardEditor::class)
            ->set('accentColor', '#abcdef')
            ->set('badgeText', 'hello')
            ->call('save')
            ->assertHasNoErrors();

        $card = BrandCard::where('business_id', $biz->id)->first();
        $this->assertEquals('#abcdef', $card->accent_color);

        $file = UploadedFile::fake()->image('logo.jpg')->size(100);
        Livewire::test(BrandCardEditor::class)
            ->set('logo', $file)
            ->call('uploadLogo')
            ->assertHasNoErrors();

        $card->refresh();
        Storage::disk('local')->assertExists($card->logo_path);
    }

    public function test_overlay_generates_jpeg_and_signed_route(): void
    {
        Storage::fake('local');
        Http::fake();

        $biz = TestCase::provisionTenant(['name' => 'Overlay Tenant']);
        $biz2 = TestCase::provisionTenant(['name' => 'Other Tenant']);

        $canvas = imagecreatetruecolor(640, 480);
        ob_start();
        imagepng($canvas);
        $png = ob_get_clean();
        imagedestroy($canvas);

        Storage::disk('local')->put('test-src.png', $png);

        DB::statement("SET app.business_id = '{$biz->id}'");
        $result = $this->overlayAction->overlay($biz->id, 'storage:test-src.png', 'license123');

        $this->assertEquals('branded', $result['status']);

        $media = BrandedMedia::find($result['media_id']);
        $path = 'branded/'.$biz->id.'/'.md5('storage:test-src.png').'-social.jpg';
        Storage::disk('local')->assertExists($path);

        $this->assertEquals(640, $result['overlay_layer']['width']);
        $this->assertEquals(480, $result['overlay_layer']['height']);

        Http::assertNothingSent();

        $url = $media->output_media_url;
        DB::statement("SET app.business_id = '{$biz->id}'");
        $response = $this->get($url);
        $response->assertStatus(200);
    }
}
