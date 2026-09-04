<?php

namespace Tests\Modules\CReviews\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class LossAlertsScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-reviews.loss-alerts'))->assertOk();

        Livewire::test(\App\Modules\CReviews\Ui\LossAlerts::class)->assertOk();
    }
}
