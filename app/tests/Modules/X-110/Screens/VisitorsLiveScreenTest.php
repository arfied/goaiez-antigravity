<?php

declare(strict_types=1);

namespace Tests\Modules\X110\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X110\Ui\VisitorsLive;
use Livewire\Livewire;
use Tests\TestCase;

class VisitorsLiveScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-110.visitors-live'))->assertOk();

        Livewire::test(VisitorsLive::class)->assertOk();
    }
}
