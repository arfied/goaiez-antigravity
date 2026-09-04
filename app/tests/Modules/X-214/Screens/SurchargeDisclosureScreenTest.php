<?php

declare(strict_types=1);

namespace Tests\Modules\X214\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X214\Ui\SurchargeDisclosure;
use Livewire\Livewire;
use Tests\TestCase;

class SurchargeDisclosureScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-214.surcharge-disclosure'))->assertOk();

        Livewire::test(SurchargeDisclosure::class)->assertOk();
    }
}
