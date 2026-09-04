<?php

namespace Tests\Modules\X181\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class TicketScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-181.ticket'))->assertOk();

        Livewire::test(\App\Modules\X181\Ui\Ticket::class)->assertOk();
    }
}
