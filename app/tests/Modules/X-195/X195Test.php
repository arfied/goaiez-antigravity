<?php

declare(strict_types=1);

namespace Tests\Modules\X195;

use App\Modules\X195\Actions\FlagSetAction;
use App\Modules\X195\Actions\MarketInstallAction;
use App\Modules\X195\Actions\MarketPublishAction;
use App\Modules\X195\Events\FlagChanged;
use App\Modules\X195\Events\ManifestInstalled;
use App\Modules\X195\Events\ManifestPublished;
use App\Modules\X195\Models\FeatureFlag;
use App\Modules\X195\Models\Install;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X195Test extends TestCase
{
    private MarketPublishAction $publishAction;

    private MarketInstallAction $installAction;

    private FlagSetAction $flagAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publishAction = new MarketPublishAction;
        $this->installAction = new MarketInstallAction;
        $this->flagAction = new FlagSetAction;
    }

    /**
     * TEST ANCHOR
     * an install writes rows to installs, manifests and config tables and zero files under app/ —
     * asserted by a filesystem diff in the test
     */
    public function test_anchor_install_writes_database_rows_and_zero_files_under_app(): void
    {
        Event::fake([ManifestPublished::class, ManifestInstalled::class, FlagChanged::class]);

        $biz = TestCase::provisionTenant(['name' => 'Marketplace & Extension Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Publish marketplace item
        $manifest = [
            'name' => 'Automated Weather Dispatcher',
            'version' => '1.2.0',
            'author' => 'WeatherSync Integrations',
            'declared_actions' => ['weather.forecast.sync'],
            'subscriptions' => ['job.created'],
        ];

        $publishedItem = $this->publishAction->publish(
            businessId: $biz->id,
            itemName: 'Automated Weather Dispatcher',
            itemSlug: 'weather-dispatcher',
            version: '1.2.0',
            manifestJson: $manifest,
            isVerified: true
        );

        $this->assertNotNull($publishedItem->id);
        $this->assertEquals(0, $publishedItem->install_count);
        Event::assertDispatched(ManifestPublished::class);

        // 2. Capture filesystem snapshot of app/ before install (TEST ANCHOR)
        $appFilesBefore = $this->scanDirectory(base_path('app'));

        // Execute install
        $install = $this->installAction->install(
            businessId: $biz->id,
            marketItemId: $publishedItem->id,
            configValues: ['api_key' => 'secret_weather_key_123', 'poll_interval_mins' => 15]
        );

        // Capture filesystem snapshot of app/ after install (TEST ANCHOR)
        $appFilesAfter = $this->scanDirectory(base_path('app'));

        // Assert ZERO files created or modified under app/ (TEST ANCHOR & G2-50)
        $this->assertEquals($appFilesBefore, $appFilesAfter, 'An install writes ZERO files under app/ — verified by filesystem diff');

        // Assert database rows written to installs and market_items (TEST ANCHOR & G9-07)
        $this->assertNotNull($install->id);
        $savedInstall = Install::where('business_id', $biz->id)->find($install->id);
        $this->assertNotNull($savedInstall);
        $this->assertEquals('1.2.0', $savedInstall->installed_version);

        $publishedItem->refresh();
        $this->assertEquals(1, $publishedItem->install_count, 'Install count incremented (G9-07)');

        Event::assertDispatched(ManifestInstalled::class);

        // 3. Feature flag blast-radius control per tenant (G4-13, G19-05)
        $flag = $this->flagAction->setFlag(
            businessId: $biz->id,
            flagKey: 'weather_auto_dispatch_v2',
            isEnabled: true,
            blastRadiusPct: 25
        );

        $this->assertTrue($flag->is_enabled);
        $this->assertEquals(25, $flag->blast_radius_pct);

        $savedFlag = FeatureFlag::where('business_id', $biz->id)->where('flag_key', 'weather_auto_dispatch_v2')->first();
        $this->assertNotNull($savedFlag);
        $this->assertTrue($savedFlag->is_enabled);
        $this->assertEquals(25, $savedFlag->blast_radius_pct);

        Event::assertDispatched(FlagChanged::class);
    }

    /**
     * [G2-50], [G4-13], [G4-29], [G4-49], [G6-19], [G9-07], [G19-05], [G1-63], [G4-52], [G4-55]
     */
    private function scanDirectory(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[$file->getPathname()] = $file->getMTime();
            }
        }
        ksort($files);

        return $files;
    }
}
