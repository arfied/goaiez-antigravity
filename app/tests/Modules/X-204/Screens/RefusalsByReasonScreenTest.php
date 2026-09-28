<?php

declare(strict_types=1);

namespace Tests\Modules\X204\Screens;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use App\Modules\X204\Models\SendPermit;
use App\Modules\X204\Models\Suppression;
use App\Modules\X204\Ui\RefusalsByReason;
use App\Services\Crm\NeverContact;
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
            ->assertSet('success', "Check for +15559990001: allowed by this screen's list. Every real text is still checked against the platform's own consent record.");

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
            ->assertSet('success', "Added +15559990001 to this screen's refusal list and stopped any follow-up sequence to them. No customer has this number, so other texts are not blocked: add them as a customer and choose Never contact them.");

        $this->assertDatabaseHas((new Suppression)->getTable(), [
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
            ->assertSet('success', "Check for +15559990001: refused by this screen's list (Reason: SUPPRESSED). Every real text is still checked against the platform's own consent record.");

        $this->get(route('x-204.refusals-by-reason'))
            ->assertOk()
            ->assertSee('+15559990001')->assertDontSee('No consent refusals recorded.');

        $this->assertDatabaseHas((new SendPermit)->getTable(), [
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

    public function test_suppress_marks_existing_customer_never_contact(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::set($biz->id);
        $this->actingAs($user);

        $customer = Customer::factory()->create(['phone' => '+15559990088', 'name' => 'John Doe Profile']);

        Livewire::test(RefusalsByReason::class)
            ->set('suppressPhone', '+15559990088')
            ->set('suppressChannel', 'sms')
            ->set('suppressReason', 'opt_out')
            ->call('suppress')
            ->assertSet('error', '')
            ->assertSet('success', "Added +15559990088 to this screen's refusal list, stopped any follow-up sequence, and marked {$customer->name} as Never contact, so no text will be sent to them.");

        $this->assertTrue(app(NeverContact::class)->state($customer)->on);
    }

    public function test_suppress_leaves_other_tenant_customer_untouched(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $otherBiz = $this->provisionTenant();
        Tenancy::set((int) $otherBiz->id);
        $customer = Customer::factory()->create(['phone' => '+15559990088']);

        Tenancy::set((int) $biz->id);
        $this->actingAs($user);

        Livewire::test(RefusalsByReason::class)
            ->set('suppressPhone', '+15559990088')
            ->set('suppressChannel', 'sms')
            ->set('suppressReason', 'opt_out')
            ->call('suppress')
            ->assertSet('error', '')
            ->assertSet('success', "Added +15559990088 to this screen's refusal list and stopped any follow-up sequence to them. No customer has this number, so other texts are not blocked: add them as a customer and choose Never contact them.");

        Tenancy::set((int) $otherBiz->id);
        $this->assertFalse(app(NeverContact::class)->state($customer)->on);
    }
}
