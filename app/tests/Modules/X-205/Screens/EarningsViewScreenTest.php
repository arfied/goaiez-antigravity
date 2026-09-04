<?php

namespace Tests\Modules\X205\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class EarningsViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-205.earnings'))->assertOk();

        Livewire::test(\App\Modules\X205\Ui\EarningsView::class)->assertOk();
    }
}
