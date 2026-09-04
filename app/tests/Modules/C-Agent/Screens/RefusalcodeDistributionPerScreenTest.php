<?php

declare(strict_types=1);

namespace Tests\Modules\CAgent\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CAgent\Ui\RefusalcodeDistributionPer;
use Livewire\Livewire;
use Tests\TestCase;

class RefusalcodeDistributionPerScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-agent.refusalcode-distribution-per'))->assertOk();

        Livewire::test(RefusalcodeDistributionPer::class)->assertOk();
    }
}
