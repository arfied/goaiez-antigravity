<?php

namespace Tests\Modules\X103;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X103\Actions\PageVariantResultAction;
use App\Modules\X103\Actions\PageVariantStartAction;
use App\Modules\X103\Actions\PageVariantStopAction;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVariant;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X108\Models\Waitlist;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\EdgeProvisionAction;
use App\Modules\X157\Actions\LatestDeploymentForPageAction;
use App\Modules\X157\Models\Deployment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Concerns\RefreshesTenantDatabase;

class PageVariantTest extends TestCase
{
    use RefreshesTenantDatabase;
    private $biz;

    private $page;

    private $zone;

    private $deploy;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        DB::statement("SET app.business_id = '{$this->biz->id}'");

        $this->zone = app(EdgeProvisionAction::class)->handle($this->biz->id, 'acme-hvac.com', true);

        $this->page = Page::create([
            'business_id' => $this->biz->id,
            'title' => 'Home',
            'slug' => 'home',
            'is_published' => true,
        ]);

        $commitId = 'commit_'.Str::random(16);
        $version = PageVersion::create([
            'business_id' => $this->biz->id,
            'page_id' => $this->page->id,
            'commit_id' => $commitId,
            'content_blocks' => [
                ['type' => 'hero', 'headline' => 'Distinctive control 4571'],
            ],
        ]);

        $this->page->update(['current_version_id' => $version->id]);

        $this->deploy = app(EdgeDeployAction::class)->handle(
            businessId: $this->biz->id,
            edgeZoneId: $this->zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $this->page->id,
            commitId: $commitId,
            businessName: $this->biz->name
        );
    }

    public function test_variant_lifecycle(): void
    {
        $startAction = app(PageVariantStartAction::class);
        $res = $startAction->handle($this->biz->id, $this->page->id, 'Distinctive variant 4572');

        $this->assertEquals('started', $res['status']);
        $this->assertNotNull($res['variant_id']);

        $row = PageVariant::find($res['variant_id']);
        $this->assertEquals('running', $row->status);

        $this->assertEquals(2, Deployment::where('page_id', $this->page->id)->where('status', 'deployed')->count());

        $controlFile = Storage::disk('local')->get("sites/{$this->deploy['deploy_hash']}.html");
        $this->assertStringContainsString('4571', $controlFile);
        $this->assertStringNotContainsString('4572', $controlFile);

        $variantFile = Storage::disk('local')->get("sites/{$res['variant_hash']}.html");
        $this->assertStringContainsString('4572', $variantFile);
        $this->assertStringNotContainsString('4571', $variantFile);

        $latest = app(LatestDeploymentForPageAction::class)->handle($this->biz->id, $this->page->id);
        $this->assertEquals($this->deploy['deploy_hash'], $latest->deploy_hash);

        $res2 = $startAction->handle($this->biz->id, $this->page->id, 'Another variant');
        $this->assertEquals('refused', $res2['status']);
        $this->assertEquals('variant_running', $res2['reason']);

        app(PageVariantStopAction::class)->handle($this->biz->id, $res['variant_id'], false);
        $this->assertEquals('stopped', $row->fresh()->status);
        $this->assertEquals('superseded', Deployment::where('deploy_hash', $res['variant_hash'])->first()->status);
        $this->assertEquals('deployed', Deployment::where('deploy_hash', $this->deploy['deploy_hash'])->first()->status);

        // test freeze
        $this->page->update(['is_tenant_edited' => false]);
        $row->delete(); // reset
        $res3 = $startAction->handle($this->biz->id, $this->page->id, 'Variant freeze');
        app(PageVariantStopAction::class)->handle($this->biz->id, $res3['variant_id'], true);
        $this->assertEquals('frozen', PageVariant::find($res3['variant_id'])->status);
    }

    public function test_refusals(): void
    {
        $startAction = app(PageVariantStartAction::class);

        $this->page->update(['is_tenant_edited' => true]);
        $res = $startAction->handle($this->biz->id, $this->page->id, 'Variant 1');
        $this->assertEquals('refused', $res['status']);
        $this->assertEquals('tenant_wording', $res['reason']);

        $this->page->update(['is_tenant_edited' => false]);
        $res2 = $startAction->handle($this->biz->id, $this->page->id, 'Distinctive control 4571');
        $this->assertEquals('refused', $res2['status']);
        $this->assertEquals('same_as_control', $res2['reason']);

        $res3 = $startAction->handle($this->biz->id, $this->page->id, $this->biz->name);
        $this->assertEquals('refused', $res3['status']);
        $this->assertEquals('brand_name', $res3['reason']);
    }

    public function test_variant_result(): void
    {
        $startAction = app(PageVariantStartAction::class);
        $res = $startAction->handle($this->biz->id, $this->page->id, 'Distinctive variant 4572');
        $row = PageVariant::find($res['variant_id']);

        $controlHash = $row->control_deploy_hash;
        $variantHash = $row->variant_deploy_hash;

        Deployment::where('deploy_hash', $controlHash)->update(['served_count' => 120]);
        Deployment::where('deploy_hash', $variantHash)->update(['served_count' => 130]);

        for ($i = 0; $i < 2; $i++) {
            Waitlist::create([
                'business_id' => $this->biz->id,
                'deploy_hash' => $controlHash,
                'customer_name' => 'Test',
                'customer_phone' => '+15125567731',
                'service_name' => 'Haircut',
                'preferred_date' => now()->addDays(2)->toDateString(),
            ]);
        }

        for ($i = 0; $i < 3; $i++) {
            Waitlist::create([
                'business_id' => $this->biz->id,
                'deploy_hash' => $variantHash,
                'customer_name' => 'Test',
                'customer_phone' => '+15125567731',
                'service_name' => 'Haircut',
                'preferred_date' => now()->addDays(2)->toDateString(),
            ]);
        }

        $resultAction = app(PageVariantResultAction::class);
        $result = $resultAction->handle($this->biz->id, $row->id);

        $this->assertEquals('Measured', $result['control']->state->value);
        $this->assertEquals('Measured', $result['variant']->state->value);
        $this->assertEquals(intdiv(2 * 10000, 120), $result['control']->ratePerTenThousand);
        $this->assertEquals(intdiv(3 * 10000, 130), $result['variant']->ratePerTenThousand);
        $this->assertEquals('variant', $result['leader']);

        Deployment::where('deploy_hash', $controlHash)->update(['served_count' => 10]);
        $result2 = $resultAction->handle($this->biz->id, $row->id);
        $this->assertEquals('InsufficientData', $result2['control']->state->value);
        $this->assertNull($result2['leader']);
    }
}
