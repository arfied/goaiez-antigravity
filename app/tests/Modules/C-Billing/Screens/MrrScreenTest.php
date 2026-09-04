<?php

namespace Tests\Modules\CBilling\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class MrrScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-billing.mrr'))->assertOk();

        Livewire::test(\App\Modules\CBilling\Ui\Mrr::class)->assertOk();
    }
}
