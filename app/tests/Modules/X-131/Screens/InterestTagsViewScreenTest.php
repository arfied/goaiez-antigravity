<?php

namespace Tests\Modules\X131\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class InterestTagsViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-131.interest-tags'))->assertOk();

        Livewire::test(\App\Modules\X131\Ui\InterestTagsView::class)->assertOk();
    }
}
