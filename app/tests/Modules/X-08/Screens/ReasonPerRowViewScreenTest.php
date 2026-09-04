<?php

namespace Tests\Modules\X08\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ReasonPerRowViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-08.reason-per-row'))->assertOk();

        Livewire::test(\App\Modules\X08\Ui\ReasonPerRowView::class)->assertOk();
    }
}
