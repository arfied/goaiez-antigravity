<?php

namespace Tests\Modules\X126\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class RefusalAnalyticsScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-126.refusal-analytics'))->assertOk();

        Livewire::test(\App\Modules\X126\Ui\RefusalAnalytics::class)->assertOk();
    }
}
