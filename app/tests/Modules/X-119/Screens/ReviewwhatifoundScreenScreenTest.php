<?php

declare(strict_types=1);

namespace Tests\Modules\X119\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X119\Ui\ReviewwhatifoundScreen;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewwhatifoundScreenScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-119.reviewwhatifound-screen'))->assertOk();

        Livewire::test(ReviewwhatifoundScreen::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-119.reviewwhatifound-screen.admin'))->assertOk();

        Livewire::test(ReviewwhatifoundScreen::class)->assertOk();
    }
}
