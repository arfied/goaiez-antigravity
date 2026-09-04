<?php

declare(strict_types=1);

namespace Tests\Modules\X145\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X145\Ui\ProposalsAppearToday;
use Livewire\Livewire;
use Tests\TestCase;

class ProposalsAppearTodayScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-145.proposals-appear-today'))->assertOk();

        Livewire::test(ProposalsAppearToday::class)->assertOk();
    }
}
