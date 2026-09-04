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
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-184.calendar'))->assertOk();

        Livewire::test(\App\Modules\X184\Ui\CalendarView::class)->assertOk();
    }
}
