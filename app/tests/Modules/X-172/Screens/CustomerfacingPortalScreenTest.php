<?php

declare(strict_types=1);

namespace Tests\Modules\X172\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X172\Ui\CustomerfacingPortal;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerfacingPortalScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-172.customerfacing-portal'))->assertOk();

        Livewire::test(CustomerfacingPortal::class)->assertOk();
    }
}
