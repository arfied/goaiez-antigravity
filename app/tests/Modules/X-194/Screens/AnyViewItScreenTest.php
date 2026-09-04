<?php

namespace Tests\Modules\X194\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class AnyViewItScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-194.any-view-it'))->assertOk();

        Livewire::test(\App\Modules\X194\Ui\AnyViewIt::class)->assertOk();
    }
}
