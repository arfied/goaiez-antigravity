<?php

declare(strict_types=1);

namespace Tests\Modules\X201\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X201\Models\Dispute;
use App\Modules\X201\Ui\DisputeQueue;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class DisputeQueueScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-201.dispute-queue'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No open disputes.');

        Tenancy::setUser($owner->id);
        Dispute::create([
            'business_id' => $biz->id,
            'invoice_id' => 4619,
            'chargeback_amount_cents' => 12345,
            'reason' => 'Distinctive reason 4619',
            'status' => 'opened',
        ]);
        Tenancy::forget();

        $this->get(route('x-201.dispute-queue'))
            ->assertOk()
            ->assertSee('Invoice #4619')
            ->assertSee('123.45')
            ->assertSee('Distinctive reason 4619')
            ->assertSee('no signature yet')
            ->assertSee('Compile evidence')
            ->assertDontSee('No open disputes.');

        Livewire::test(DisputeQueue::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-201.dispute-queue.admin'))->assertOk();

        Livewire::test(DisputeQueue::class)->assertOk();
    }
}
