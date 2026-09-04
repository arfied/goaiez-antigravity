<?php

declare(strict_types=1);

namespace Tests\Modules\X126\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X126\Ui\RefusalAnalytics;
use Livewire\Livewire;
use Tests\TestCase;

class RefusalAnalyticsScreenTest extends TestCase
{
    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-126.refusal-analytics.admin'))->assertOk();

        Livewire::test(RefusalAnalytics::class)->assertOk();
    }
}
