<?php

declare(strict_types=1);

namespace Tests\Modules\X176\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X176\Models\SchemaSnapshot;
use App\Modules\X176\Ui\SeoTabWebsite;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class SeoTabWebsiteScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-176.seo-tab-website'))->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No schema snapshots published.');

        Tenancy::setUser($owner->id);
        SchemaSnapshot::create([
            'business_id' => $biz->id,
            'page_id' => 4477,
            'entity_type' => 'LocalBusiness',
            'json_ld' => ['@type' => 'LocalBusiness'],
            'commit_id' => 'commit_distinctive_4477'
        ]);
        Tenancy::forget();

        $this->get(route('x-176.seo-tab-website'))->assertOk()
            ->assertSee('Page #4477')
            ->assertSee('[LocalBusiness]')
            ->assertSee('commit_distinctive_4477')
            ->assertDontSee('No schema snapshots published.');

        Livewire::test(SeoTabWebsite::class)->assertOk();
    }
}
