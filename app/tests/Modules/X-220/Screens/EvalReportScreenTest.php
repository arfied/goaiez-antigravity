<?php

declare(strict_types=1);

namespace Tests\Modules\X220\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X220\Ui\EvalReport;
use Livewire\Livewire;
use Tests\TestCase;

class EvalReportScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-220.eval-report'))->assertOk();

        Livewire::test(EvalReport::class)->assertOk();
    }
}
