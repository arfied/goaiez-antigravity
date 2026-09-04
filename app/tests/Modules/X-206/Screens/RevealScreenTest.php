<?php

declare(strict_types=1);

namespace Tests\Modules\X206\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X206\Ui\Reveal;
use Livewire\Livewire;
use Tests\TestCase;

class RevealScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-206.reveal'))->assertOk();

        Livewire::test(Reveal::class)->assertOk();
    }
}
