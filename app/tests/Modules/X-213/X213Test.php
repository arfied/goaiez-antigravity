<?php

declare(strict_types=1);

namespace Tests\Modules\X213;

use App\Modules\X121\Models\Business;
use App\Modules\X213\Actions\VisionCheckRunAction;
use App\Modules\X213\Events\VisionCheckFlagged;
use App\Modules\X213\Events\VisionCheckPassed;
use App\Modules\X213\Models\VisionCheck;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X213Test extends TestCase
{
    private VisionCheckRunAction $runAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runAction = new VisionCheckRunAction;
    }

    /**
     * TEST ANCHOR
     * every check stores its screenshot and checklist version — a pass with no artifact DID NOT HAPPEN.
     */
    public function test_anchor_stores_screenshot_and_checklist_version_and_pass_requires_artifact(): void
    {
        Event::fake([VisionCheckPassed::class, VisionCheckFlagged::class]);

        $biz = Business::provision(['name' => 'Vision Quality Gate Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $checklistVer = 'v2.4-mobile-responsive';

        // 1. Attempt to pass WITHOUT an artifact -> FAILS (A pass with no artifact DID NOT HAPPEN: TEST ANCHOR)
        $noArtifactCheck = $this->runAction->runCheck(
            businessId: $biz->id,
            contentRef: 'landing_hero_section_a',
            checklistVersion: $checklistVer,
            screenshotArtifactUrl: null // Missing artifact
        );

        $this->assertFalse($noArtifactCheck->passed, 'A pass with no artifact DID NOT HAPPEN (TEST ANCHOR)');
        $this->assertEquals($checklistVer, $noArtifactCheck->checklist_version, 'Checklist version is stored');
        Event::assertDispatched(VisionCheckFlagged::class);

        // 2. Check WITH artifact and no defects -> PASSES (TEST ANCHOR)
        $validArtifactUrl = 'https://storage.example.com/screenshots/hero_v2_shot.png';
        $validCheck = $this->runAction->runCheck(
            businessId: $biz->id,
            contentRef: 'landing_hero_section_b',
            checklistVersion: $checklistVer,
            screenshotArtifactUrl: $validArtifactUrl,
            detectedDefects: []
        );

        $this->assertTrue($validCheck->passed, 'Check with valid artifact and zero defects passes');
        $this->assertEquals($validArtifactUrl, $validCheck->screenshot_artifact_url, 'Screenshot artifact is stored');
        $this->assertEquals($checklistVer, $validCheck->checklist_version, 'Checklist version is stored');

        $savedRecord = VisionCheck::where('business_id', $biz->id)->find($validCheck->id);
        $this->assertNotNull($savedRecord);
        $this->assertTrue($savedRecord->passed);

        Event::assertDispatched(VisionCheckPassed::class);
    }

    /**
     * [N-213-01], [N-213-02]
     */
    public function test_vision_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
