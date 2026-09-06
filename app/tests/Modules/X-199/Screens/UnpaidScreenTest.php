<?php

declare(strict_types=1);

namespace Tests\Modules\X199\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X199\Ui\Unpaid;
use Livewire\Livewire;
use Tests\TestCase;

class UnpaidScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-199.unpaid'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('<h1 class="sr-only">Unpaid Invoices</h1>', false);

        Livewire::test(Unpaid::class)->assertOk();
    }
}
