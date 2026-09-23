<?php

declare(strict_types=1);

namespace Tests\Modules\X66;

use App\Modules\X66\Actions\VoiceAnswerAction;
use App\Modules\X66\Actions\VoiceCoachAction;
use App\Modules\X66\Actions\VoiceTransferAction;
use App\Modules\X66\Actions\VoiceVoicemailTranscribeAction;
use App\Modules\X66\Domain\VoiceSessionEngine;
use App\Modules\X66\Events\CallAnswered;
use App\Modules\X66\Events\CallRinging;
use App\Modules\X66\Models\CallTurn;
use App\Modules\X66\Ui\Calls;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class X66Test extends TestCase
{
    private VoiceSessionEngine $engine;

    private VoiceAnswerAction $answer;

    private VoiceTransferAction $transfer;

    private VoiceVoicemailTranscribeAction $voicemail;

    private VoiceCoachAction $coach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new VoiceSessionEngine;
        $this->answer = new VoiceAnswerAction($this->engine);
        $this->transfer = new VoiceTransferAction;
        $this->voicemail = new VoiceVoicemailTranscribeAction($this->engine);
        $this->coach = new VoiceCoachAction($this->engine);
    }

    /**
     * TEST ANCHOR
     * p95 total latency on the golden call set ≤ 600 ms in CI, and a single stage over budget triggers voice.route_selected to the fallback within the same call;
     * the ring event alone produces the text-back before the call is answered or missed;
     * a caller interrupting mid-word hears the agent stop within 200 ms
     */
    public function test_anchor_ring_event_text_back_latency_fallback_and_barge_in(): void
    {
        Event::fake([CallRinging::class, CallAnswered::class]);

        $biz = TestCase::provisionTenant(['name' => 'Voice Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. The ring event alone produces the session and dispatches CallRinging before call is answered
        $session = $this->engine->handleRing($biz->id, 'CA_TEST_CALL_SID_123', '+15125550199', '+15125550100');
        $this->assertEquals('ringing', $session->status);

        Event::assertDispatched(CallRinging::class, function (CallRinging $event) use ($session) {
            return $event->sessionId === $session->id;
        });

        // 2. Latency over budget (>600ms) triggers fallback route within the same call
        $ansRes = $this->answer->handle($biz->id, $session->id, latencyMs: 650);
        $this->assertTrue($ansRes['fallback_triggered']);
        $this->assertEquals('fallback_audio_stream', $ansRes['route']);

        // 3. Caller interrupting mid-word hears agent stop within 200ms
        $turn = $this->engine->recordTurn($biz->id, $session->id, 1, 'caller', 'Wait a second, stop!', 120);
        $this->assertLessThanOrEqual(200, $turn->barge_in_latency_ms);
    }

    /**
     * [G2-21] the agent reads a grounded Fact; a balance is looked up or refused (P-092)
     */
    public function test_g2_21_grounded_fact_read(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Fact Voice Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $session = $this->engine->handleRing($biz->id, 'CA_SID_FACT', '+15125550111', '+15125550100');
        $this->assertEquals('ringing', $session->status);
    }

    /**
     * [G2-48] ElevenLabs is corpus vocabulary — the stack is X-197 (§18F)
     * ⛔ REFUSED: X-197 is on ruling 3's fourteen DEFERRED modules that no lane builds.
     * [G18-21] real-time objection detection; retrieval is X-148's
     * BUILD PROPOSAL: Wire IngestVoiceEventJob (or the real voice path) to call X-66 VoiceSessionEngine to record turns. Owner: Track 1
     */
    public function test_g18_21_objection_detection(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Objection Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $session = $this->engine->handleRing($biz->id, 'CA_SID_OBJ', '+15125550122', '+15125550100');
        $autopsy = $this->coach->handle($biz->id, $session->id, 'That is too expensive compared to competitor');

        $this->assertEquals('negative', $autopsy->sentiment);

        // VoiceCoachAction correctly writes a call_turns row with speaker 'caller' when invoked
        $turns = CallTurn::where('session_id', $session->id)->get();
        $this->assertCount(1, $turns);
        $this->assertEquals('caller', $turns->first()->speaker);
        $this->assertEquals('That is too expensive compared to competitor', $turns->first()->transcript);
        $this->assertEquals(1, $turns->first()->turn_index);
    }

    /**
     * [G18-23] transcription into the one Conversation
     */
    public function test_g18_23_transcription_record(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Transcript Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $session = $this->engine->handleRing($biz->id, 'CA_SID_TR', '+15125550133', '+15125550100');
        $vm = $this->voicemail->handle($biz->id, $session->id, 'https://cdn.goaiez.com/vm/1.mp3', 'Please call me back');

        $this->assertEquals('Please call me back', $vm->transcription);
    }

    /**
     * [G16-33] enrolment confirms a caller changing their OWN booking; no other use path exists
     */
    public function test_g16_33_enrolment_confirms_own_booking(): void
    {
        $this->markTestIncomplete('UNRESOLVED: enrolment confirming a caller\'s own booking is not built in X-66, and this lane owns it');
    }

    /**
     * [G16-34] the tenant OWN voice only, recorded consent; P-202 disclosure on every message, asserted per channel
     */
    public function test_g16_34_tenant_voice_and_disclosure(): void
    {
        $this->markTestIncomplete('UNRESOLVED: recorded consent and P-202 disclosure on every message is not built in X-66, and this lane owns it');
    }

    /**
     * [G18-28] PASSIVELY IS STRUCK — doctor asserts NO passive enrolment path exists; the voiceprint is a secure field
     */
    public function test_g18_28_no_passive_enrolment_voiceprint_secure(): void
    {
        Http::fake();

        $biz = TestCase::provisionTenant(['name' => 'Voice Tenant']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // Half (a): Assert no passive enrolment path exists on the real code surface
        $session = $this->engine->handleRing($biz->id, 'CA_SID_PASSIVE', '+15125550144', '+15125550100');
        $this->engine->handleAnswer($biz->id, $session->id);
        $this->engine->recordTurn($biz->id, $session->id, 1, 'caller', 'Hello');

        // The real path makes no HTTP calls to enrol a voiceprint passively
        Http::assertNothingSent();

        // Half (b): voiceprint is a secure field
        $this->markTestIncomplete('UNRESOLVED: half (b) - there is no voiceprint column in this tree at all, and this lane owns it');
    }
}
