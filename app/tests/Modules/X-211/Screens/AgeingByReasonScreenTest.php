<?php

namespace Tests\Modules\X211\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class AgeingByReasonScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-211.ageing-by-reason'))->assertOk();

        Livewire::test(\App\Modules\X211\Ui\AgeingByReason::class)->assertOk();
    }
}
