<?php

namespace Tests\Modules\X209\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class OnetapApprovalCardScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-209.onetap-approval-card'))->assertOk();

        Livewire::test(\App\Modules\X209\Ui\OnetapApprovalCard::class)->assertOk();
    }
}
