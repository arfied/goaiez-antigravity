<?php

declare(strict_types=1);

namespace Tests\Modules\CReviews\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class QaReportScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-reviews.qa-report'))->assertOk();

        Livewire::test(\App\Modules\CReviews\Ui\QaReport::class)->assertOk();
    }
}
