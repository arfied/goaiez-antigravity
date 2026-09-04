<?php

namespace Tests\Modules\X08\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class RiskListViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-08.risk-list'))->assertOk();

        Livewire::test(\App\Modules\X08\Ui\RiskListView::class)->assertOk();
    }
}
