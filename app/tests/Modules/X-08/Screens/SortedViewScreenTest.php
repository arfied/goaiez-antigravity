<?php

namespace Tests\Modules\X08\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class SortedViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-08.sorted'))->assertOk();

        Livewire::test(\App\Modules\X08\Ui\SortedView::class)->assertOk();
    }
}
