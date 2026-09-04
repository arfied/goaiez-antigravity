<?php

namespace Tests\Modules\X166\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class MarginByJobScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-166.margin-by-job'))->assertOk();

        Livewire::test(\App\Modules\X166\Ui\MarginByJob::class)->assertOk();
    }
}
