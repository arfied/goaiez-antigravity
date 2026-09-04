<?php

declare(strict_types=1);

namespace Tests\Modules\X167\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X167\Ui\Reorders;
use Livewire\Livewire;
use Tests\TestCase;

class ReordersScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-167.reorders'))->assertOk();

        Livewire::test(Reorders::class)->assertOk();
    }
}
