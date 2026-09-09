<?php

declare(strict_types=1);

namespace Tests\Modules\X157;

use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\EdgeProvisionAction;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EdgeDeployTenancyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_page_from_another_business_is_not_read_into_the_published_artifact(): void
    {
        Storage::fake('local');
        
        $a = self::provisionTenant([
            'name' => 'Local Tenant A',
        ]);
        $b = self::provisionTenant([
            'name' => 'Local Tenant B',
        ]);

        Tenancy::set((int) $b->id);
        $bPage = Page::create(['business_id' => $b->id, 'title' => 'Distinctive Title B', 'slug' => 'distinctive-b']);

        Tenancy::set((int) $a->id);
        PageVersion::create([
            'business_id' => $a->id,
            'page_id' => $bPage->id,
            'commit_id' => 'commit_tenancy_1',
            'content_blocks' => [
                ['type' => 'text', 'content' => 'Tenant A content'],
            ],
        ]);

        $zoneA = app(EdgeProvisionAction::class)->handle($a->id, 'tenancya.example.com', true);

        $res = app(EdgeDeployAction::class)->handle(
            businessId: $a->id,
            edgeZoneId: $zoneA->id,
            pageId: $bPage->id,
            commitId: 'commit_tenancy_1',
            businessName: 'Local Biz A'
        );

        $this->assertEquals('deployed', $res['status']);
        $this->assertFalse(Storage::disk('local')->exists("sites/{$res['deploy_hash']}.llms.txt"));
    }
}
