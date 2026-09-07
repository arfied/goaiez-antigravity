<?php

namespace Tests\Modules\X179;

use App\Modules\X179\Domain\TemplateEngine;
use Tests\TestCase;

class X179Test extends TestCase
{
    public function test_capabilities()
    {
        $engine = new TemplateEngine;
        $this->assertTrue($engine->enforceHeaderNaming('My Header'));
        $this->assertEquals('Welcome! We see you use Laravel.', $engine->extractTechStackToOpener('Laravel'));
    }

    /**
     * @group G11-07
     */
    public function test_extraction_feeds_the_opener_g11_07()
    {
        $biz = TestCase::provisionTenant(['name' => 'Test Tenant', 'currency' => 'USD']);
        \Illuminate\Support\Facades\DB::statement("SET app.business_id = '{$biz->id}'");

        $engine = new TemplateEngine();
        $this->assertEquals('Welcome!', $engine->extractTechStackToOpener(''));

        $action = new \App\Modules\X179\Actions\ContentExtractAction();
        $content = $action->extractContent($biz->id, 1, 'site', 'verbatim desc', 'React');

        $this->assertEquals('React', $content->tech_stack);
        $this->assertEquals('verbatim desc', $content->service_description);

        $opener = $engine->extractTechStackToOpener($content->tech_stack);
        $this->assertEquals('Welcome! We see you use React.', $opener);
    }
}
