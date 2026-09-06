<?php

declare(strict_types=1);

namespace Tests\Modules\X176;

use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\EdgeProvisionAction;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * [G3-34] LLMs.txt Injection
 */
final class LlmsTxtTest extends TestCase
{
    use DatabaseTransactions;

    /** (R245) */
    public function test_llms_txt_is_generated_on_deploy(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant([
            'name' => 'Local Tenant 6',
        ]);
        Tenancy::set((int) $biz->id);

        $page = Page::create(['business_id' => $biz->id, 'title' => 'Services', 'slug' => 'services']);
        PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page->id,
            'commit_id' => 'commit_llms_1',
            'content_blocks' => [
                ['type' => 'text', 'content' => 'We offer great plumbing services.'],
                ['type' => 'text', 'content' => 'Call us today!']
            ]
        ]);

        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'llms1.example.com', true);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            pageId: $page->id,
            commitId: 'commit_llms_1',
            businessName: 'Local Biz 6'
        );

        $this->assertEquals('deployed', $res['status']);
        
        $llmsPath = "sites/{$res['deploy_hash']}.llms.txt";
        $this->assertTrue(Storage::disk('local')->exists($llmsPath));
        
        $content = Storage::disk('local')->get($llmsPath);
        $this->assertStringContainsString('# Local Biz 6', $content);
        $this->assertStringContainsString('## Services', $content);
        $this->assertStringContainsString('Path: /services', $content);
        $this->assertStringContainsString('We offer great plumbing services.', $content);
    }

    /** (R245) */
    public function test_llms_txt_is_not_generated_without_page(): void
    {
        Storage::fake('local');
        $biz = self::provisionTenant([
            'name' => 'Local Tenant 7',
        ]);
        Tenancy::set((int) $biz->id);

        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'llms2.example.com', true);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
        );

        $this->assertEquals('deployed', $res['status']);
        
        $llmsPath = "sites/{$res['deploy_hash']}.llms.txt";
        $this->assertFalse(Storage::disk('local')->exists($llmsPath));
    }
}
