<?php
namespace Tests\Modules\X179;
use Tests\TestCase;
use App\Modules\X179\Domain\TemplateEngine;
class X179Test extends TestCase {
    public function test_capabilities() {
        $engine = new TemplateEngine();
        $this->assertTrue($engine->enforceHeaderNaming('My Header'));
        $this->assertEquals('Welcome! We see you use Laravel.', $engine->extractTechStackToOpener('Laravel'));
    }
}
