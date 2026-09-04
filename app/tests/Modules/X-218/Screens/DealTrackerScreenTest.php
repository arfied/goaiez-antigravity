<?php

declare(strict_types=1);

namespace Tests\Modules\X218\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X218\Ui\DealTracker;
use Livewire\Livewire;
use Tests\TestCase;

class DealTrackerScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-218.deal-tracker'))->assertOk();

        Livewire::test(DealTracker::class)->assertOk();
    }
}
