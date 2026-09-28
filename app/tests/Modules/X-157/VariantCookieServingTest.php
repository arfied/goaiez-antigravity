<?php

namespace Tests\Modules\X157;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X103\Actions\PageVariantStartAction;
use App\Modules\X103\Actions\PageVariantStopAction;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\EdgeProvisionAction;
use App\Modules\X157\Domain\DnsResolver;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class VariantCookieServingTest extends TestCase
{
    use RefreshesTenantDatabase;

    private $biz;

    private $page;

    private $variantId;

    protected function setUp(): void
    {
        parent::setUp();
        app()->instance(DnsResolver::class, new class implements DnsResolver
        {
            public function cname(string $domain): ?string
            {
                if ($domain === 'acme-roofing.test') {
                    return parse_url(config('app.url'), PHP_URL_HOST);
                }

                return null;
            }
        });

        Storage::fake('local');
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        DB::statement("SET app.business_id = '{$this->biz->id}'");

        $zone = app(EdgeProvisionAction::class)->handle($this->biz->id, 'acme-roofing.test', true);

        DB::table('custom_domain_requests')->where('domain', 'acme-roofing.test')->delete();
        DB::table('custom_domain_requests')->insert([
            'business_id' => $this->biz->id,
            'domain' => 'acme-roofing.test',
            'status' => 'verified',
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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

        app(EdgeDeployAction::class)->handle(
            businessId: $this->biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $this->page->id,
            commitId: $commitId,
            businessName: $this->biz->name
        );

        $startRes = app(PageVariantStartAction::class)->handle($this->biz->id, $this->page->id, 'Distinctive variant 4572');
        $this->variantId = $startRes['variant_id'];
    }

    public function test_variant_cookies(): void
    {
        Tenancy::forgetAll();
        $id = $this->variantId;

        $resVariant = $this->withCookie('gz_arm_'.$id, 'variant')->get('http://acme-roofing.test/');
        $resVariant->assertSee('4572')->assertDontSee('4571');

        $resControl = $this->withCookie('gz_arm_'.$id, 'control')->get('http://acme-roofing.test/');
        $resControl->assertSee('4571')->assertDontSee('4572');

        $resGpc = $this->withCookie('gz_arm_'.$id, 'variant')->withHeader('Sec-GPC', '1')->get('http://acme-roofing.test/');
        $resGpc->assertSee('4571')->assertDontSee('4572');
        $resGpc->assertCookieMissing('gz_arm_'.$id);

        unset($this->defaultHeaders['Sec-GPC']);
        if (method_exists($this, 'withoutHeader')) {
            $this->withoutHeader('Sec-GPC');
        }
        unset($this->defaultCookies['gz_arm_'.$id]);
        $resNoCookie = $this->get('http://acme-roofing.test/');
        $resNoCookie->assertCookie('gz_arm_'.$id);

        try {
            $resNoCookie->assertCookie('gz_arm_'.$id, 'control');
            $chosen = 'control';
        } catch (ExpectationFailedException $e) {
            $resNoCookie->assertCookie('gz_arm_'.$id, 'variant');
            $chosen = 'variant';
        }
        $this->assertTrue(in_array($chosen, ['control', 'variant'], true));
        if ($chosen === 'control') {
            $resNoCookie->assertSee('4571')->assertDontSee('4572');
        } else {
            $resNoCookie->assertSee('4572')->assertDontSee('4571');
        }

        app(PageVariantStopAction::class)->handle($this->biz->id, $id, false);

        $resStopped = $this->withCookie('gz_arm_'.$id, 'variant')->get('http://acme-roofing.test/');
        $resStopped->assertSee('4571')->assertDontSee('4572');
    }
}
