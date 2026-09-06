<?php

namespace Tests\Modules\X182;

use App\Modules\X182\Domain\SocialEngine;
use Tests\TestCase;

class X182Test extends TestCase
{
    /**
     * [G2-08]
     */
    public function test_schedule_with_pass()
    {
        $engine = new SocialEngine;
        $this->assertTrue($engine->scheduleWithPass(['social_pass' => true]));
        $this->assertFalse($engine->scheduleWithPass([]));
        $this->assertFalse($engine->scheduleWithPass(['social_pass' => false]));
    }

    /**
     * [G12-10]
     */
    public function test_use_tenant_history()
    {
        $engine = new SocialEngine;
        $this->assertTrue($engine->useTenantHistory(['item1']));
        $this->assertFalse($engine->useTenantHistory([]));
    }

    /**
     * [G12-12]
     */
    public function test_thread_into_conversation()
    {
        $engine = new SocialEngine;
        $this->assertTrue($engine->threadIntoConversation(1, 10));
        $this->assertFalse($engine->threadIntoConversation(1, 0));
    }

    /**
     * [G12-17]
     */
    public function test_tone_per_channel()
    {
        $engine = new SocialEngine;
        $this->assertTrue($engine->tonePerChannel('facebook', 'casual'));
        $this->assertFalse($engine->tonePerChannel('facebook', 'sarcastic'));
    }

    /**
     * [G12-26]
     */
    public function test_persona_per_channel()
    {
        $engine = new SocialEngine;
        $this->assertTrue($engine->personaPerChannel('facebook', 'BrandRep'));
        $this->assertFalse($engine->personaPerChannel('facebook', ''));
    }

    /**
     * [G12-32]
     */
    public function test_ensure_real_job_photos()
    {
        $engine = new SocialEngine;
        $this->assertTrue($engine->ensureRealJobPhotos('real_job.jpg'));
        $this->assertFalse($engine->ensureRealJobPhotos('stock_photo.jpg'));
    }

    /**
     * [G12-38]
     */
    public function test_filter_low_ratings()
    {
        $engine = new SocialEngine;
        $this->assertTrue($engine->filterLowRatings(5));
        $this->assertFalse($engine->filterLowRatings(3));
    }
}
