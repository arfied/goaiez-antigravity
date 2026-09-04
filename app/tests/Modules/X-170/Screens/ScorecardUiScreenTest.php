<?php

declare(strict_types=1);

namespace Tests\Modules\X170\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X170\Ui\ScorecardUi;
use Livewire\Livewire;
use Tests\TestCase;

class ScorecardUiScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-170.scorecard'))->assertOk();

        Livewire::test(ScorecardUi::class)->assertOk();
    }
}
