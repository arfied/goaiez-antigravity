<?php

declare(strict_types=1);

namespace Tests\Modules\X66;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X188\Models\NumberAssignment;
use App\Modules\X188\Models\NumberPool;
use App\Modules\X66\Ui\Calls;
use App\Support\Tenancy;
use Database\Factories\CallSessionFactory;
use Database\Factories\CallTurnFactory;
use Database\Factories\X66VoicemailFactory;
use Livewire\Livewire;
use Tests\TestCase;

class CallsScreenTest extends TestCase
{
    public function test_calls_screen_renders_states(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Calls Tenant', 'currency' => 'USD']);
        $user = User::factory()->create();

        Tenancy::actingAs($biz->id, function () use ($biz, $user) {
            Tenancy::setUser($user->id);

            // 4. Pool-number state: WITH assignment
            $poolNumber = '+15559876543';
            $pool = NumberPool::create([
                'business_id' => $biz->id,
                'phone_number' => $poolNumber,
                'area_code' => '555',
                'carrier_name' => 'telnyx',
                'status' => 'available',
                'complaint_count' => 0,
            ]);
            $assignment = NumberAssignment::create([
                'business_id' => $biz->id,
                'phone_number_id' => $pool->id,
                'assigned_at' => now(),
                'status' => 'active',
            ]);

            Livewire::test(Calls::class)
                ->assertSee($poolNumber)
                ->assertDontSee('Failed to load number')
                ->assertDontSee('We couldn\'t load the assigned number');

            // 1. Empty + SAMPLE
            Livewire::test(Calls::class)
                ->assertSee('No calls yet') // empty state invitation
                ->assertSee('SAMPLE'); // SAMPLE badge

            // 4. Pool-number state: NO number assignment
            // Delete only rows WE created.
            $assignment->delete();

            Livewire::test(Calls::class)
                ->assertSeeHtml('<span class="text-ink-3 italic">None</span>')
                ->assertDontSee('Failed to load number')
                ->assertSee('No calls yet');

            // 2. Default - Two sessions
            $completedSession = CallSessionFactory::new()->create(['business_id' => $biz->id,
                'from_phone' => '+11111111111',
                'status' => 'completed',
            ]);

            $missedSession = CallSessionFactory::new()->create(['business_id' => $biz->id,
                'from_phone' => '+22222222222',
                'status' => 'missed',
            ]);

            CallTurnFactory::new()->create(['business_id' => $biz->id,
                'session_id' => $completedSession->id,
                'transcript' => 'I am calling about a quote.',
            ]);

            CallTurnFactory::new()->create(['business_id' => $biz->id,
                'session_id' => $missedSession->id,
                'transcript' => 'This is a missed call turn.',
            ]);

            X66VoicemailFactory::new()->create(['business_id' => $biz->id,
                'call_session_id' => $completedSession->id,
                'transcription' => 'Voicemail for completed call.',
            ]);

            Livewire::test(Calls::class)
                ->assertSee('+11111111111')
                ->assertSee('+22222222222')
                ->assertSee('Missed')
                ->assertSee('Completed')
                ->assertDontSee('SAMPLE')
                ->assertDontSee('No calls yet');

            // 3. The row action - select
            Livewire::test(Calls::class)
                ->call('select', $completedSession->id)
                ->assertSee('I am calling about a quote.')
                ->assertSee('Voicemail for completed call.')
                ->assertDontSee('This is a missed call turn.');
        });
    }

    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::actingAs($biz->id, function () use ($biz) {
            $completedSession = CallSessionFactory::new()->create(['business_id' => $biz->id,
                'from_phone' => '+11111111111',
                'status' => 'completed',
            ]);
        });

        $this->get(route('x-66.calls'))
            ->assertOk()
            ->assertSee('+11111111111');
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-66.calls.admin'))
            ->assertOk();

        Livewire::test(Calls::class)->assertOk();
    }
}
