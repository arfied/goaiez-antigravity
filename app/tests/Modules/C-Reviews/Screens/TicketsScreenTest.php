<?php

namespace Tests\Modules\CReviews\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class TicketsScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('c-reviews.tickets'))->assertOk();

        Livewire::test(\App\Modules\CReviews\Ui\Tickets::class)->assertOk();
    }
}
