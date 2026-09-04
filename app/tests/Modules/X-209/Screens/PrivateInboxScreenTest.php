<?php

declare(strict_types=1);

namespace Tests\Modules\X209\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X209\Ui\PrivateInbox;
use Livewire\Livewire;
use Tests\TestCase;

class PrivateInboxScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-209.private-inbox'))->assertOk();

        Livewire::test(PrivateInbox::class)->assertOk();
    }
}
