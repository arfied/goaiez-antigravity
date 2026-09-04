<?php

namespace Tests\Modules\X132\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class PersonTimelineViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-132.person-timeline'))->assertOk();

        Livewire::test(\App\Modules\X132\Ui\PersonTimelineView::class)->assertOk();
    }
}
