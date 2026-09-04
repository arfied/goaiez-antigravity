<?php

declare(strict_types=1);

namespace Tests\Modules\X102\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class OfflineFormInboxScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-102.offline-form-inbox'))->assertOk();

        Livewire::test(\App\Modules\X102\Ui\OfflineFormInbox::class)->assertOk();
    }
}
