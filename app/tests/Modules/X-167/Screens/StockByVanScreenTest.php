<?php

namespace Tests\Modules\X167\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class StockByVanScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-167.stock-by-van'))->assertOk();

        Livewire::test(\App\Modules\X167\Ui\StockByVan::class)->assertOk();
    }
}
