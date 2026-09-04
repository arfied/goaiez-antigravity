<?php

namespace Tests\Modules\X203\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class RestorationtestLogScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-203.restorationtest-log'))->assertOk();

        Livewire::test(\App\Modules\X203\Ui\RestorationtestLog::class)->assertOk();
    }
}
