<?php

declare(strict_types=1);

namespace Tests\Modules\CReviews\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CReviews\Ui\Tickets;
use Livewire\Livewire;
use Tests\TestCase;

class TicketsScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-reviews.tickets'))->assertOk();

        Livewire::test(Tickets::class)->assertOk();
    }
}
