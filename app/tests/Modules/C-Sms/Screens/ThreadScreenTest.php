<?php

declare(strict_types=1);

namespace Tests\Modules\CSms\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CSms\Models\SmsComposition;
use App\Modules\CSms\Ui\Thread;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ThreadScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-sms.thread'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No texts yet.');

        Tenancy::setUser($owner->id);
        SmsComposition::create([
            'business_id' => $biz->id,
            'recipient_phone' => '+15125554611',
            'message_class' => 'transactional',
            'body' => 'Distinctive text 4611',
            'segments_count' => 1,
            'encoding' => 'gsm7',
            'status' => 'sent',
        ]);
        Tenancy::forget();

        $this->get(route('c-sms.thread'))
            ->assertOk()
            ->assertSee('+15125554611')
            ->assertSee('Distinctive text 4611')
            ->assertSee('sent')
            ->assertDontSee('No texts yet.');

        Livewire::test(Thread::class)->assertOk();
    }

    /**
     * Proves the component wires the tenant's sms_compositions to the view.
     */
    public function test_screen_displays_tenant_sms(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $sms = SmsComposition::create([
            'business_id' => $biz->id,
            'recipient_phone' => '+15555551234',
            'message_class' => 'conversational',
            'body' => 'Hello from tenant',
            'segments_count' => 1,
            'encoding' => 'gsm',
            'status' => 'delivered',
            'scheduled_at' => now(),
        ]);

        $this->get(route('c-sms.thread'))->assertOk();

        Livewire::test(Thread::class)
            ->assertOk()
            ->assertSee($sms->body);
    }
}
