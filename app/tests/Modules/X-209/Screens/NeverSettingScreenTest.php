<?php

namespace Tests\Modules\X209\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class NeverSettingScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-209.never-setting'))->assertOk();

        Livewire::test(\App\Modules\X209\Ui\NeverSetting::class)->assertOk();
    }
}
