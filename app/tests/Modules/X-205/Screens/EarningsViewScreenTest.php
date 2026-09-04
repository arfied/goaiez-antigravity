<?php

declare(strict_types=1);

namespace Tests\Modules\X205\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class EarningsViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-205.earnings'))->assertOk();

        Livewire::test(\App\Modules\X205\Ui\EarningsView::class)->assertOk();
    }
}
