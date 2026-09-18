<?php

declare(strict_types=1);

namespace Tests\Modules\X204\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X204\Ui\RefusalsByReason;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class RefusalsByReasonScreenTest extends TestCase
{
    public function test_screen_renders_for_owner(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::set($biz->id);
        $this->actingAs($user);

        $this->get(route('x-204.refusals-by-reason'))->assertOk();

        Livewire::test(RefusalsByReason::class)->assertOk();
    }
}
