<?php

declare(strict_types=1);

namespace Tests\Modules\X126\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

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
