<?php

namespace Tests\Modules\X176\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class SeoTabWebsiteScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-176.seo-tab-website'))->assertOk();

        Livewire::test(\App\Modules\X176\Ui\SeoTabWebsite::class)->assertOk();
    }
}
