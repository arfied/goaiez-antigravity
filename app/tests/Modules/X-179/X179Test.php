<?php

namespace Tests\Modules\X179;

use App\Modules\X179\Actions\ContentExtractAction;
use App\Modules\X179\Domain\TemplateEngine;
use Illuminate\Support\Facades\DB;
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
        DB::statement("SET app.business_id = '{$biz->id}'");

        $engine = new TemplateEngine;
        $this->assertEquals('Welcome!', $engine->extractTechStackToOpener(''));

        $action = new ContentExtractAction;
        $content = $action->extractContent($biz->id, 1, 'site', 'verbatim desc', 'React');

        $this->assertEquals('React', $content->tech_stack);
        $this->assertEquals('verbatim desc', $content->service_description);

        $opener = $engine->extractTechStackToOpener($content->tech_stack);
        $this->assertEquals('Welcome! We see you use React.', $opener);
    }

    /**
     * @group G10-09
     */
    public function test_boilerplate_exclusion_g10_09()
    {
        $biz = TestCase::provisionTenant(['name' => 'Boilerplate Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $rawPage = <<<'HTML'
<nav>this is a very long nav section with lots of links and text. this is a very long nav section with lots of links and text. this is a very long nav section with lots of links and text.</nav>
<body>body content</body>
<cookie-banner>cookie banner text here, quite long as well. cookie banner text here, quite long as well.</cookie-banner>
<footer>this is a very long footer section with lots of links and text. this is a very long footer section with lots of links and text. this is a very long footer section with lots of links and text.</footer>
HTML;

        $action = new ContentExtractAction;
        $content = $action->extractContent($biz->id, 1, 'site', $rawPage, 'React');

        $this->assertEquals('<body>body content</body>', $content->service_description);
    }

    /**
     * @group G6-13
     */
    public function test_ecommerce_detection_g6_13()
    {
        $biz = TestCase::provisionTenant(['name' => 'Ecommerce Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $action = new ContentExtractAction;

        // 1. Marker in footer is detected, but footer is excluded from service_description
        $rawPage1 = '<body>Main Content</body><footer>Powered by cdn.shopify.com</footer>';
        $content1 = $action->extractContent($biz->id, 1, 'site', $rawPage1);

        $this->assertEquals('Shopify', $content1->tech_stack);
        $this->assertEquals('<body>Main Content</body>', $content1->service_description);

        // 2. No marker leaves tech_stack null
        $rawPage2 = '<body>Plain HTML Page</body>';
        $content2 = $action->extractContent($biz->id, 2, 'site', $rawPage2);

        $this->assertNull($content2->tech_stack);

        // 3. Explicit techStack survives despite markers
        $rawPage3 = '<body>Content</body><footer><div class="woocommerce">Powered by WooCommerce</div></footer>';
        $content3 = $action->extractContent($biz->id, 3, 'site', $rawPage3, 'React');

        $this->assertEquals('React', $content3->tech_stack);

        // 4. Prose does not trigger WooCommerce detection
        $rawPage4 = '<body>We migrated off WooCommerce last year.</body>';
        $content4 = $action->extractContent($biz->id, 4, 'site', $rawPage4);
        $this->assertNull($content4->tech_stack);

        // 5. Substring /image/ does not trigger Magento detection
        $rawPage5 = '<body><img src="/image/hero.png"></body>';
        $content5 = $action->extractContent($biz->id, 5, 'site', $rawPage5);
        $this->assertNull($content5->tech_stack);
    }
}
