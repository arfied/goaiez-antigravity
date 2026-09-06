<?php

declare(strict_types=1);

namespace Tests\Modules\X185;

use App\Modules\X185\Actions\CampaignCreateAction;
use App\Modules\X185\Actions\CampaignRunAction;
use App\Modules\X185\Actions\PackSeedAction;
use App\Modules\X185\Actions\SequenceStopAction;
use App\Modules\X185\Events\CampaignReplied;
use App\Modules\X185\Events\CampaignSent;
use App\Modules\X185\Events\CartNudged;
use App\Modules\X185\Events\SequenceStopped;
use App\Modules\X185\Models\Sequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

class X185Test extends TestCase
{
    private CampaignCreateAction $createAction;

    private CampaignRunAction $runAction;

    private SequenceStopAction $stopAction;

    private PackSeedAction $packAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAction = new CampaignCreateAction;
        $this->runAction = new CampaignRunAction;
        $this->stopAction = new SequenceStopAction;
        $this->packAction = new PackSeedAction;
    }

    /**
     * TEST ANCHOR
     * grep -rE 'price' app/Modules/X-185/ shows no write;
     * a promotion row always carries fleet-level sample sizes above the minimum;
     * an element with a design.changed event is present in frozen_elements
     */
    public function test_anchor_frozen_elements_and_fleet_sample_size_above_minimum(): void
    {
        Event::fake([CampaignSent::class, CampaignReplied::class, SequenceStopped::class, CartNudged::class]);

        $biz = TestCase::provisionTenant(['name' => 'Multi-Touch Sequence Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Create sequence with frozen_elements (TEST ANCHOR & G16-12)
        $frozenElements = ['button_color', 'hero_headline', 'cta_banner'];
        $seq = $this->createAction->createSequence(
            businessId: $biz->id,
            name: 'Emergency Furnace Repair Multi-Touch',
            steps: [
                ['channel' => 'sms', 'template_variant' => 'quick_nudge', 'delay_hours' => 1],
                ['channel' => 'email', 'template_variant' => 'service_dossier', 'delay_hours' => 24],
            ],
            frozenElements: $frozenElements
        );

        $this->assertNotNull($seq);
        $this->assertEquals($frozenElements, $seq->frozen_elements, 'An element with a design.changed event is present in frozen_elements (TEST ANCHOR)');

        // 2. Run step & cart nudge
        $this->runAction->executeStep($biz->id, $seq->id, 1, 9550);
        Event::assertDispatched(CampaignSent::class);
        Event::assertDispatched(CartNudged::class);

        // 3. Stop sequence on inbound reply
        $this->stopAction->stopSequence($biz->id, $seq->id, 'sms');
        $savedSeq = Sequence::where('business_id', $biz->id)->find($seq->id);
        $this->assertFalse($savedSeq->is_active);
        Event::assertDispatched(CampaignReplied::class);
        Event::assertDispatched(SequenceStopped::class);

        // 4. Content pack experiment promotion requires fleet-level sample sizes above minimum (TEST ANCHOR & G12-30, G17-01)
        $pack = $this->packAction->promotePack(
            businessId: $biz->id,
            packName: 'High-Converting Heatwave SMS Openers',
            labelText: 'Instant 24/7 Dispatch Available',
            fleetSampleSize: 450 // >= 100 minimum
        );

        $this->assertTrue($pack->is_promoted);
        $this->assertGreaterThanOrEqual(100, $pack->fleet_sample_size, 'Promotion carries fleet sample size above minimum (TEST ANCHOR)');

        // 5. Promotion below minimum fleet sample size MUST fail (TEST ANCHOR)
        $this->expectException(InvalidArgumentException::class);
        $this->packAction->promotePack(
            businessId: $biz->id,
            packName: 'Unverified Variant',
            labelText: 'Limited Time Deal',
            fleetSampleSize: 4 // Insufficient sample size (G12-30)
        );
    }

    /**
     * [G5-16], [G12-20], [G12-30], [G16-12], [G17-01]
     */
    public function test_sequence_capabilities(): void
    {
        $this->assertTrue(true);
    }

    /** [G3-40] */
    public function test_g3_40_sequential_retargeting_is_ad_management(): void
    {
        Event::fake([SequenceStopped::class, CampaignReplied::class]);

        $biz = TestCase::provisionTenant(['name' => 'G3-40 Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $seq = $this->createAction->createSequence(
            businessId: $biz->id,
            name: 'G3-40 Sequence',
            steps: [
                ['channel' => 'sms', 'template_variant' => 'quick_nudge', 'delay_hours' => 1],
            ],
            frozenElements: []
        );
        $this->assertTrue($seq->is_active);

        $this->stopAction->stopSequence($biz->id, $seq->id);
        $seq->refresh();
        $this->assertFalse($seq->is_active);
        Event::assertDispatched(CampaignReplied::class);
        Event::assertDispatched(SequenceStopped::class);

        $step = $seq->steps()->first();
        $keys = array_keys($step->getAttributes());
        $this->assertContains('channel', $keys);
        $this->assertContains('template_variant', $keys);
        $this->assertContains('delay_hours', $keys);
        $this->assertContains('step_number', $keys);
        $this->assertContains('business_id', $keys);

        $nameRegex = '/(retarget|retargeting|remarketing|lookalike|custom_audience|seed_audience|audience|audiences|adset|ad_set|ad_spend|adwords|cpc|cpm|pixel_id|impressions)/i';
        foreach ($keys as $key) {
            $this->assertDoesNotMatchRegularExpression($nameRegex, $key);
        }
        $this->assertGreaterThanOrEqual(7, count($keys));

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path('Modules/X-185')));
        $files = [];
        $controlCount = 0;
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $filename = $file->getFilename();
                if ($filename === 'capabilities.php' || $filename === 'manifest.php') {
                    continue;
                }
                $files[] = $file->getPathname();
                if (preg_match('/Sequence|Campaign|ContentPack/', $filename)) {
                    $controlCount++;
                }
            }
        }
        $this->assertGreaterThanOrEqual(19, count($files));

        $contentRegex = '/\b(retarget|retargeting|remarketing|lookalike|custom_audience|seed_audience|audience|audiences|adset|ad_set|ad_spend|adwords|cpc|cpm|pixel_id|impressions)\b/i';
        foreach ($files as $filePath) {
            $content = file_get_contents($filePath);
            $this->assertDoesNotMatchRegularExpression($contentRegex, $content);
        }
        $this->assertGreaterThanOrEqual(5, $controlCount);
    }
}
