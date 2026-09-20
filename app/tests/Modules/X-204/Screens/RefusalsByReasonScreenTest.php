<?php

declare(strict_types=1);

namespace Tests\Modules\X204\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X204\Models\SendPermit;
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

        SendPermit::create([
            'business_id' => $biz->id,
            'recipient_phone' => '+15551234567',
            'channel' => 'sms',
            'permit_status' => 'refused',
            'refusal_reason' => 'archived',
        ]);

        $this->get(route('x-204.refusals-by-reason'))
            ->assertOk()
            ->assertSee('+15551234567');

        Livewire::test(RefusalsByReason::class)
            ->assertOk()
            ->assertSee('+15551234567');
    }
}

    public function test_decide_and_suppress_controls(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::set($biz->id);
        $this->actingAs($user);

        // 1. Decide on a clean number, assert screen is still empty of refusals
        Livewire::test(RefusalsByReason::class)
            ->set('decidePhone', '+15559990001')
            ->set('decideChannel', 'sms')
            ->call('decide')
            ->assertSet('error', '')
            ->assertSet('success', 'Check for +15559990001: Granted. This feeds the margin lists; nothing downstream is wired to it yet.');

        $this->get(route('x-204.refusals-by-reason'))
            ->assertOk()
            ->assertDontSee('+15559990001')->assertSee('No consent refusals recorded.');

        // 2. Suppress the number
        Livewire::test(RefusalsByReason::class)
            ->set('suppressPhone', '+15559990001')
            ->set('suppressChannel', 'sms')
            ->set('suppressReason', 'opt_out')
            ->call('suppress')
            ->assertSet('error', '')
            ->assertSet('success', 'Number +15559990001 has been suppressed. This feeds the margin lists; nothing downstream is wired to it yet.');

        $this->assertDatabaseHas((new \App\Modules\X204\Models\Suppression)->getTable(), [
            'recipient_phone' => '+15559990001',
            'channel' => 'sms',
            'reason' => 'opt_out',
        ]);

        // 3. Decide again, assert refusal is shown
        Livewire::test(RefusalsByReason::class)
            ->set('decidePhone', '+15559990001')
            ->set('decideChannel', 'sms')
            ->call('decide')
            ->assertSet('error', '')
            ->assertSet('success', 'Check for +15559990001: Refused (Reason: SUPPRESSED). This feeds the margin lists; nothing downstream is wired to it yet.');

        $this->get(route('x-204.refusals-by-reason'))
            ->assertOk()
            ->assertSee('+15559990001')->assertDontSee('No consent refusals recorded.');

        $this->assertDatabaseHas((new \App\Modules\X204\Models\SendPermit)->getTable(), [
            'recipient_phone' => '+15559990001',
            'permit_status' => 'refused',
        ]);

        // Refusal case: empty phone for decide
        Livewire::test(RefusalsByReason::class)
            ->set('decidePhone', '')
            ->call('decide')
            ->assertSet('error', 'Phone number is required to decide.');

        // Refusal case: empty phone for suppress
        Livewire::test(RefusalsByReason::class)
            ->set('suppressPhone', '')
            ->call('suppress')
            ->assertSet('error', 'Phone number is required to suppress.');
    }
