<?php

declare(strict_types=1);

namespace Tests\Modules\CSms\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CSms\Models\SmsComposition;
use App\Modules\CSms\Ui\PernumberComplaintMonitoring;
use App\Modules\X204\Models\Suppression;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class PernumberComplaintMonitoringScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-sms.pernumber-complaint-monitoring'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No texts sent yet.');

        Tenancy::setUser($owner->id);
        SmsComposition::create([
            'business_id' => $biz->id,
            'recipient_phone' => '+15125554617',
            'message_class' => 'transactional',
            'body' => 'Distinctive text 4617 a',
            'segments_count' => 1,
            'encoding' => 'gsm7',
            'status' => 'sent',
        ]);
        SmsComposition::create([
            'business_id' => $biz->id,
            'recipient_phone' => '+15125554617',
            'message_class' => 'transactional',
            'body' => 'Distinctive text 4617 b',
            'segments_count' => 1,
            'encoding' => 'gsm7',
            'status' => 'halted',
        ]);
        Suppression::create([
            'business_id' => $biz->id,
            'recipient_phone' => '+15125554617',
            'channel' => 'sms',
            'reason' => 'Distinctive stop 4617',
            'suppressed_at' => now(),
        ]);
        Tenancy::forget();

        $this->get(route('c-sms.pernumber-complaint-monitoring'))
            ->assertOk()
            ->assertSee('+15125554617')
            ->assertSee('1 sent · 1 halted')
            ->assertSee('stopped')
            ->assertDontSee('No texts sent yet.');

        Livewire::test(PernumberComplaintMonitoring::class)->assertOk();
    }
}
