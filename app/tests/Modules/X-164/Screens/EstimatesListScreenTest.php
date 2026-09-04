<?php

declare(strict_types=1);

namespace Tests\Modules\X164\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class EstimatesListScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-164.estimates-list'))->assertOk();

        Livewire::test(\App\Modules\X164\Ui\EstimatesList::class)->assertOk();
    }
}
