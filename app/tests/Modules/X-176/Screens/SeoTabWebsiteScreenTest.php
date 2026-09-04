<?php

declare(strict_types=1);

namespace Tests\Modules\X176\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X176\Ui\SeoTabWebsite;
use Livewire\Livewire;
use Tests\TestCase;

class SeoTabWebsiteScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-176.seo-tab-website'))->assertOk();

        Livewire::test(SeoTabWebsite::class)->assertOk();
    }
}
