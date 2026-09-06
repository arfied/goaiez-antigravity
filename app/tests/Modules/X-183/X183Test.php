<?php

namespace Tests\Modules\X183;

use App\Modules\X183\Actions\ContentGateAction;
use App\Modules\X183\Domain\GateEngine;
use App\Modules\X183\Events\ContentGated;
use App\Modules\X183\Events\ContentRejected;
use App\Modules\X183\Models\ContentDraft;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X183Test extends TestCase
{
    public function test_capabilities()
    {
        $engine = new GateEngine;
        $this->assertTrue($engine->requireDoubleConsent(true, true));
        $this->assertEquals('ApprovalDesk', $engine->escalateNegativeComment('this is bad'));
        $this->assertTrue($engine->requireRealData(true));
        $this->assertTrue($engine->prePublishGate(true, true));
        $this->assertTrue($engine->noSamplePrices('real price $10'));
    }

    /**
     * [G12-29]
     */
    public function test_g12_29_rejects_sample_prices()
    {
        $biz = TestCase::provisionTenant(['name' => 'Test Biz', 'currency' => 'USD']);

        Tenancy::actingAs($biz->id, function () use ($biz) {
            Event::fake();

            $draft = ContentDraft::create([
                'business_id' => $biz->id,
                'title' => 'Test Draft',
                'body_text' => 'This is a SAMPLE PRICE for a service.',
                'is_case_study' => false,
                'has_double_consent' => false,
                'is_approved' => true,
                'is_published' => true,
            ]);

            $action = new ContentGateAction;
            $result = $action->evaluateGate($biz->id, $draft->id);

            $this->assertFalse($result->passed);
            $this->assertEquals('Draft contains placeholder sample price', $result->rejection_reason);

            $draft->refresh();
            $this->assertFalse($draft->is_approved);
            $this->assertFalse($draft->is_published);

            Event::assertDispatched(ContentRejected::class, function ($event) use ($biz, $draft) {
                return $event->businessId === $biz->id && $event->draftId === $draft->id && $event->reason === 'Draft contains placeholder sample price';
            });
        });
    }

    /**
     * [G2-11]
     */
    public function test_g2_11_rejects_case_study_without_consent()
    {
        $biz = TestCase::provisionTenant(['name' => 'Test Biz', 'currency' => 'USD']);

        Tenancy::actingAs($biz->id, function () use ($biz) {
            Event::fake();

            $draft = ContentDraft::create([
                'business_id' => $biz->id,
                'title' => 'Test Draft',
                'body_text' => 'A valid case study body.',
                'is_case_study' => true,
                'has_double_consent' => false,
                'is_approved' => true,
                'is_published' => true,
            ]);

            $action = new ContentGateAction;
            $result = $action->evaluateGate($biz->id, $draft->id);

            $this->assertFalse($result->passed);
            $this->assertEquals('R36: Case study requires verified double consent before publication', $result->rejection_reason);

            $draft->refresh();
            $this->assertFalse($draft->is_approved);
            $this->assertFalse($draft->is_published);

            Event::assertDispatched(ContentRejected::class);
        });
    }

    /**
     * [G5-35]
     */
    public function test_g5_35_enforces_real_data_double_consent()
    {
        $biz = TestCase::provisionTenant(['name' => 'Test Biz', 'currency' => 'USD']);

        Tenancy::actingAs($biz->id, function () use ($biz) {
            Event::fake();

            $draft = ContentDraft::create([
                'business_id' => $biz->id,
                'title' => 'Test Draft',
                'body_text' => 'Another valid case study body.',
                'is_case_study' => true,
                'has_double_consent' => false,
                'is_approved' => true,
                'is_published' => true,
            ]);

            $action = new ContentGateAction;
            $result = $action->evaluateGate($biz->id, $draft->id);

            $this->assertFalse($result->passed);
            $this->assertEquals('R36: Case study requires verified double consent before publication', $result->rejection_reason);

            $draft->refresh();
            $this->assertFalse($draft->is_approved);
            $this->assertFalse($draft->is_published);

            Event::assertDispatched(ContentRejected::class);
        });
    }

    /**
     * [G6-04]
     */
    public function test_g6_04_publishes_case_study_with_consent()
    {
        $biz = TestCase::provisionTenant(['name' => 'Test Biz', 'currency' => 'USD']);

        Tenancy::actingAs($biz->id, function () use ($biz) {
            Event::fake();

            $draft = ContentDraft::create([
                'business_id' => $biz->id,
                'title' => 'Test Draft',
                'body_text' => 'Valid case study text.',
                'is_case_study' => true,
                'has_double_consent' => true,
                'is_approved' => false,
                'is_published' => false,
            ]);

            $action = new ContentGateAction;
            $result = $action->evaluateGate($biz->id, $draft->id);

            $this->assertTrue($result->passed);
            $this->assertNull($result->rejection_reason);

            $draft->refresh();
            $this->assertTrue($draft->is_approved);
            $this->assertTrue($draft->is_published);

            Event::assertDispatched(ContentGated::class, function ($event) use ($biz, $draft) {
                return $event->businessId === $biz->id && $event->draftId === $draft->id && $event->passed === true;
            });
        });
    }
}
