<?php

namespace Tests\Modules\CMail\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ComplaintbounceBoardScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-mail.complaintbounce-board'))->assertOk();

        Livewire::test(\App\Modules\CMail\Ui\ComplaintbounceBoard::class)->assertOk();
    }
}
