<?php
namespace Tests\Modules\X182;
use Tests\TestCase;
use App\Modules\X182\Domain\SocialEngine;
class X182Test extends TestCase {
    public function test_capabilities() {
        $engine = new SocialEngine();
        $this->assertTrue($engine->scheduleWithPass(['social_pass' => true]));
        $this->assertTrue($engine->useTenantHistory(['item1']));
        $this->assertTrue($engine->threadIntoConversation(1, 10));
        $this->assertTrue($engine->tonePerChannel('facebook', 'casual'));
        $this->assertTrue($engine->personaPerChannel('facebook', 'BrandRep'));
        $this->assertTrue($engine->ensureRealJobPhotos('real_job.jpg'));
        $this->assertFalse($engine->ensureRealJobPhotos('stock_photo.jpg'));
        $this->assertTrue($engine->filterLowRatings(5));
        $this->assertFalse($engine->filterLowRatings(3));
    }
}
