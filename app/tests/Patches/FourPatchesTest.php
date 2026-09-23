<?php

declare(strict_types=1);

namespace Tests\Patches;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * THE FOUR PATCHES, AS TESTS THAT FAIL TODAY.
 *
 * ⛔ These are not the fixes. I do not have the four source files, and writing a
 * "patched" version of code I cannot read would be invention — the same class of
 * defect the patches exist to remove.
 *
 * ⭐⭐⭐ WHAT I CAN WRITE IS THE PROOF. Each test below fails against the current
 * behaviour and passes when the fix lands. That is `P-210` working the right way
 * round: the assertions are authored by someone who is NOT building the fix, so
 * the fixer cannot quietly redefine "done".
 *
 * ⛔ ALL FOUR SHARE ONE SHAPE: THE CODE REPORTS SUCCESS FOR WORK IT DID NOT DO.
 * Not a crash, not a wrong answer — a confident claim about something that never
 * happened. It is the same defect as a status column reading CLASSIFIED while
 * 725 specs existed, and a heartbeat that cannot tell an empty queue from a dead
 * worker. It has been this programme's characteristic failure at every layer.
 *
 * Run: php artisan test --group=patches
 */
#[Group('patches')]
final class FourPatchesTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────
    // PATCH 1 · ConversationalVoiceAgent fabricates VAPI_CALL_ID_{uniqid()}
    //
    // ⛔⛔ It invents the ONE artifact the Definition of Done says cannot be
    // invented. Condition ⑤ exists precisely because the swarm cannot mint a
    // carrier's id — and this class mints one when its credential is missing.
    //
    // ⭐ It also gates §257 shadow mode: shadow requires every transport behind
    // one interface that refuses on shadow=true, and this class calls an
    // endpoint directly. Shadow is unsafe until this lands.
    // ─────────────────────────────────────────────────────────────────────

    #[Test]
    public function an_unconfigured_voice_agent_refuses_instead_of_inventing_a_call_id(): void
    {
        config(['services.voice.token' => null]);

        $result = $this->placeVoiceCall('+15550100');

        $this->assertFalse(
            $result['placed'],
            'With no credential the agent reported a placed call. It must REFUSE BEFORE THE REQUEST.'
        );

        $this->assertNotEmpty(
            $result['refusal_reason'] ?? '',
            'A refusal with no reason is a support ticket with no answer — name the missing credential.'
        );

        $this->assertStringNotContainsString(
            'VAPI_CALL_ID_',
            (string) ($result['call_sid'] ?? ''),
            'A self-minted call id was returned. Nothing in this system may mint an external artifact id.'
        );
    }

    #[Test]
    public function a_configured_voice_agent_returns_the_vendors_own_id_or_nothing(): void
    {
        $result = $this->placeVoiceCall('+15550101');

        if (($result['placed'] ?? false) === false) {
            $this->assertEmpty($result['call_sid'] ?? '', 'A refused call must carry no id at all.');

            return;
        }

        $this->assertMatchesRegularExpression(
            '/^[A-Za-z0-9_-]{8,}$/',
            (string) $result['call_sid'],
            "The call sid must be the vendor's. A generated placeholder is not evidence a call happened."
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // PATCH 2 · AiModel::costOf() returns 0 for image models
    //
    // ⛔ Image models are priced PER IMAGE and the method reasons in tokens, so
    // every image the platform generates costs nothing, forever, while the
    // balance looks healthy the whole time.
    //
    // ⚠️ And §275–§276 are about to generate images at volume across 200
    // templates, so the window where this is cheap is closing.
    // ─────────────────────────────────────────────────────────────────────

    #[Test]
    public function an_image_model_refuses_token_costing_rather_than_returning_zero(): void
    {
        $model = $this->imageModel();

        try {
            $cost = $this->costOf($model, tokens: 1_000);
            $this->fail(
                "costOf() returned {$cost} for an image model. Zero is not a price — it is free "
                .'image generation forever. Throw for image models and add costOfImages().'
            );
        } catch (\LogicException) {
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function an_image_model_with_no_price_row_refuses_rather_than_guessing(): void
    {
        // ⛔ P-092 applies to OUR costs too: looked up or REFUSED, never generated.
        //    Do not invent the price to make the test pass.
        $model = $this->imageModel(priced: false);

        $this->expectException(\RuntimeException::class);
        $this->costOfImages($model, count: 1);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PATCH 3 · NumberPoolRouter writes sticky on every round-robin pick
    //
    // ⛔ So after message 1 every subsequent message resolves to the same
    // number. Rotation is documented and does not happen.
    //
    // ⚠️ READ BEFORE FIXING: this makes the rotation REAL for the first time,
    // and the owner has ruled that the docblock sentence describing it as
    // "bypass carrier velocity filters" is STRIPPED. The rotation stays as
    // honest load-spreading across the tenant's own registered numbers.
    // ─────────────────────────────────────────────────────────────────────

    #[Test]
    public function outbound_selection_rotates_across_the_pool(): void
    {
        $pool = $this->numberPool(size: 4);

        $picked = [];
        for ($i = 0; $i < 8; $i++) {
            $picked[] = $this->selectOutbound($pool, toPerson: "person-{$i}");
        }

        $this->assertGreaterThan(
            1,
            count(array_unique($picked)),
            'Every pick returned the same number. Selection is writing the sticky mapping; '
            .'only the INBOUND handler may write it.'
        );
    }

    #[Test]
    public function an_existing_conversation_keeps_its_sticky_number(): void
    {
        // ⭐ The half that must NOT break: a person who already has a thread
        //    keeps talking to the same number. Rotation is for NEW conversations.
        $pool = $this->numberPool(size: 4);
        $first = $this->selectOutbound($pool, toPerson: 'person-sticky');
        $this->markInboundReply($pool, person: 'person-sticky', number: $first);

        $this->assertSame(
            $first,
            $this->selectOutbound($pool, toPerson: 'person-sticky'),
            'A person with an established thread was moved to a different number.'
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // PATCH 4 · OmniAssistant says "I have autonomously triggered"
    //           with the LLM call commented out
    //
    // ⛔⛔ It claims to have acted. It did not act. §281's N-281-06 now makes an
    // honest outcome a LAW rather than a bug fix: the response reports what
    // actually happened, including "I could not".
    // ─────────────────────────────────────────────────────────────────────

    #[Test]
    public function the_assistant_never_claims_an_action_it_did_not_take(): void
    {
        $reply = $this->askOmni('turn on review invites');

        if (($reply['action_invoked'] ?? null) === null) {
            foreach (['autonomously triggered', 'I have set up', 'I have enabled', 'done for you'] as $claim) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $claim,
                    (string) $reply['text'],
                    "The assistant claimed '{$claim}' while invoking nothing."
                );
            }
        }
    }

    #[Test]
    public function the_assistant_reports_its_real_state_when_the_router_is_not_wired(): void
    {
        $reply = $this->askOmni('turn on review invites');

        $this->assertNotEmpty($reply['text']);
        $this->assertTrue(
            ($reply['action_invoked'] ?? null) !== null || ($reply['could_not'] ?? false) === true,
            'The assistant neither invoked an action nor said it could not. Silence about a '
            .'failure reads as success.'
        );
    }

    #[Test]
    public function the_module_count_in_user_facing_copy_is_not_stale(): void
    {
        $reply = $this->askOmni('what can you do');

        $this->assertStringNotContainsString(
            '98 modules',
            (string) $reply['text'],
            'The assistant quotes a stale module count. The roster is 119.'
        );
    }

    // ── wiring — these bind to the real classes when the patches land ──

    /** @return array{placed:bool, call_sid?:string, refusal_reason?:string} */
    private function placeVoiceCall(string $to): array
    {
        $this->markTestIncomplete('Bind to ConversationalVoiceAgent when Patch 1 lands.');
    }

    private function imageModel(bool $priced = true): object
    {
        $this->markTestIncomplete('Bind to the ai_models row for the image model.');
    }

    private function costOf(object $model, int $tokens): int
    {
        $this->markTestIncomplete('Bind to AiModel::costOf().');
    }

    private function costOfImages(object $model, int $count): int
    {
        $this->markTestIncomplete('Bind to AiModel::costOfImages() — added by Patch 2.');
    }

    /** @return list<string> */
    private function numberPool(int $size): array
    {
        $this->markTestIncomplete('Bind to the tenant number pool.');
    }

    private function selectOutbound(array $pool, string $toPerson): string
    {
        $this->markTestIncomplete('Bind to NumberPoolRouter::select().');
    }

    private function markInboundReply(array $pool, string $person, string $number): void
    {
        $this->markTestIncomplete('Bind to the inbound handler — the ONLY writer of sticky.');
    }

    /** @return array{text:string, action_invoked?:string|null, could_not?:bool} */
    private function askOmni(string $prompt): array
    {
        $this->markTestIncomplete('Bind to OmniAssistant.');
    }
}
