<?php

namespace Tests\Modules\CMail\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class WarmupCalendarsPerScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('c-mail.warmup-calendars-per'))->assertOk();

        Livewire::test(\App\Modules\CMail\Ui\WarmupCalendarsPer::class)->assertOk();
    }
}
