<?php

declare(strict_types=1);

namespace Tests\Modules\X66;

use App\Models\User;
use App\Modules\X188\Models\NumberAssignment;
use App\Modules\X188\Models\NumberPool;
use App\Modules\X66\Models\CallSession;
use App\Modules\X66\Models\CallTurn;
use App\Modules\X66\Models\Voicemail;
use App\Modules\X66\Ui\Calls;
use App\Support\Tenancy;
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

            // Just check what is there
            $assignment = NumberAssignment::where('business_id', $biz->id)->first();
            if (! $assignment) {
                // If it isn't there, create one. We can bypass RLS for NumberPool if we need to.
                // Wait, provisionTenant DOES assign a number, let's assume it's there.
            }

            // 4. Pool-number state: WITH assignment (default from provisionTenant)
            Livewire::test(Calls::class)
                ->assertDontSee('Failed to load number')
                ->assertDontSee('We couldn\'t load the assigned number');

            // 1. Empty + SAMPLE
            Livewire::test(Calls::class)
                ->assertSee('No calls yet') // empty state invitation
                ->assertSee('SAMPLE'); // SAMPLE badge

            // 4. Pool-number state: NO number assignment
            NumberAssignment::where('business_id', $biz->id)->delete();

            Livewire::test(Calls::class)
                ->assertSee('None')
                ->assertDontSee('Failed to load number')
                ->assertSee('No calls yet');

            // 2. Default - Two sessions
            $completedSession = CallSession::create([
                'business_id' => $biz->id,
                'call_sid' => 'sid-1',
                'from_phone' => '+11111111111',
                'to_phone' => '+15559998888',
                'status' => 'completed',
                'latency_ms' => 150,
            ]);

            $missedSession = CallSession::create([
                'business_id' => $biz->id,
                'call_sid' => 'sid-2',
                'from_phone' => '+22222222222',
                'to_phone' => '+15559998888',
                'status' => 'missed',
                'latency_ms' => 0,
            ]);

            CallTurn::create([
                'business_id' => $biz->id,
                'session_id' => $completedSession->id,
                'speaker' => 'caller',
                'transcript' => 'I am calling about a quote.',
            ]);

            CallTurn::create([
                'business_id' => $biz->id,
                'session_id' => $missedSession->id,
                'speaker' => 'caller',
                'transcript' => 'This is a missed call turn.',
            ]);

            Voicemail::create([
                'business_id' => $biz->id,
                'call_session_id' => $completedSession->id,
                'audio_url' => 'https://example.com/audio1.mp3',
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
}
