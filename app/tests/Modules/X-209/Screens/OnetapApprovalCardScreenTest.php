<?php

declare(strict_types=1);

namespace Tests\Modules\X209\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X209\Ui\OnetapApprovalCard;
use Livewire\Livewire;
use Tests\TestCase;

class OnetapApprovalCardScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner, 'email' => uniqid().'@example.com']);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-209.onetap-approval-card'))->assertOk();

        Livewire::test(OnetapApprovalCard::class)->assertOk();
    }
}
