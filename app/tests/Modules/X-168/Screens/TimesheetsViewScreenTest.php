<?php

declare(strict_types=1);

namespace Tests\Modules\X168\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X168\Ui\TimesheetsView;
use Livewire\Livewire;
use Tests\TestCase;

class TimesheetsViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-168.timesheets'))->assertOk();

        Livewire::test(TimesheetsView::class)->assertOk();
    }
}
