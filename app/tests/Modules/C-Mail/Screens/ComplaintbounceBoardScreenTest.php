<?php

declare(strict_types=1);

namespace Tests\Modules\CMail\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class ComplaintbounceBoardScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-mail.complaintbounce-board'))->assertOk();

        Livewire::test(\App\Modules\CMail\Ui\ComplaintbounceBoard::class)->assertOk();
    }
}
