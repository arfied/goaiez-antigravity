<?php

declare(strict_types=1);

namespace Tests\Modules\X206\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X206\Ui\Connections;
use Livewire\Livewire;
use Tests\TestCase;

class ConnectionsScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-206.connections'))->assertOk();

        Livewire::test(Connections::class)->assertOk();
    }
}
