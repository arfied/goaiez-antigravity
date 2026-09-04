<?php

namespace Tests\Modules\X140\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ProposedPagesViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-140.proposed-pages'))->assertOk();

        Livewire::test(\App\Modules\X140\Ui\ProposedPagesView::class)->assertOk();
    }
}
