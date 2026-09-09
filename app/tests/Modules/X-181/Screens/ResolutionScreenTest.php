<?php

declare(strict_types=1);

namespace Tests\Modules\X181\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X181\Models\QaTicket;
use App\Modules\X181\Ui\Resolution;
use Livewire\Livewire;
use Tests\TestCase;

class ResolutionScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-181.resolution'))->assertOk();

        Livewire::test(Resolution::class)->assertOk();
    }

    public function test_screen_renders_awaiting_csat_pill(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        QaTicket::create([
            'business_id' => $biz->id,
            'subject' => 'HTTP Pill Test',
            'arrived_at' => now(),
            'status' => 'resolved',
            'resolved_at' => now(),
            'csat_requested_at' => now(),
            'sla_due_at' => now()->addHours(1),
        ]);

        $this->get(route('x-181.resolution'))->assertOk()->assertSee('Awaiting CSAT');
    }
}
