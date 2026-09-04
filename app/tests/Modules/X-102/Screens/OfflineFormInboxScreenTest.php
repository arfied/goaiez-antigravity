<?php

declare(strict_types=1);

namespace Tests\Modules\X102\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X102\Ui\OfflineFormInbox;
use Livewire\Livewire;
use Tests\TestCase;

class OfflineFormInboxScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-102.offline-form-inbox'))->assertOk();

        Livewire::test(OfflineFormInbox::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-102.offline-form-inbox.admin'))->assertOk();

        Livewire::test(OfflineFormInbox::class)->assertOk();
    }
}
