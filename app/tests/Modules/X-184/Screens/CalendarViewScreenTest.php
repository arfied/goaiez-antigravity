<?php

namespace Tests\Modules\X184\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class CalendarViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-184.calendar'))->assertOk();

        Livewire::test(\App\Modules\X184\Ui\CalendarView::class)->assertOk();
    }
}
