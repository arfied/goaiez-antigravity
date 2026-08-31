<?php

declare(strict_types=1);

namespace Tests\Support\AgentEvals;

use App\Models\Business;
use App\Models\Conversation;
use App\Services\Agent\AgentComposer;
use App\Services\Agent\AgentReplyDraft;
use App\Services\Agent\AgentSkills;
use App\Services\Agent\AgentSkillSet;
use App\Support\Tenancy;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Runs one arm of one eval — the only place a scripted model output meets the real
 * composer.
 *
 * ## ⛔ THE VENDOR IS FAKED AT THE HTTP BOUNDARY, NEVER AT `AiRouter`
 *
 * The reasoning is `KnowledgeIngestTest`'s and `AgentComposerTest`'s, and it is
 * sharper for a suite whose subject is *what the platform does with model output*:
 * stubbing `AiRouter` would take `AiSpend`, the credit balance, the cost cap, the
 * model-kind guard and the provider client out of the path, so the eval would
 * exercise the lint and nothing the lint sits on. What is replaced here is one
 * endpoint.
 *
 * ⚠️ **`Http::fake()` APPENDS RATHER THAN REPLACES**, so the fake is installed once
 * per arm inside {@see self::run()} and never in a `beforeEach`. A second call
 * registering a different body for the same URL queues *behind* the first — the
 * defect `AnswerAgentTurnJobTest` names, where an outage test asserted against a
 * healthy model and failed on an unrelated line.
 *
 * ## ⚠️ THE SKILL SET IS RESOLVED AFTER THE SEED, AND THAT ORDER IS LOAD-BEARING
 *
 * R13 reads the tenant's grounding at resolution time. Resolving before the seed
 * ran gives every case a bare skill set whatever it configured — which is exactly
 * L7's M19 shape one level up: the fixture would make the thing under test
 * unreachable, and the price and link cases that pair a bare tenant with a
 * configured one would silently become two copies of the bare one.
 */
final class AgentEvalRunner
{
    /**
     * Drive `$case` with `$modelOutput` and hand back what happened.
     */
    public static function run(AgentEvalCase $case, Business $business, string $modelOutput): AgentEvalRun
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'id' => 'msg_eval',
                'type' => 'message',
                'stop_reason' => 'end_turn',
                'content' => [['type' => 'text', 'text' => $modelOutput]],
                'usage' => ['input_tokens' => 1_000, 'output_tokens' => 120],
            ]),
        ]);

        $draft = Tenancy::actingAs((int) $business->id, function () use ($case, $business): AgentReplyDraft {
            ($case->seed)($business);

            $snippets = $case->snippets === null ? [] : ($case->snippets)();

            // ⚠️ **AFTER THE SEED.** See the class docblock.
            // ⛔ **ONE THREAD, RESOLVED AND WRITTEN ON** (4271). The composer now
            // takes the conversation, because a booking link rides a short link
            // minted per send. Two different threads here would resolve the
            // skills against one and mint against another — harmless today and
            // exactly the kind of drift the runner exists to keep out.
            $conversation = Conversation::factory()->create();

            $skills = app(AgentSkills::class)->forThread($conversation);

            return app(AgentComposer::class)->write(
                customerMessage: $case->customerMessage,
                conversation: $conversation,
                skills: $skills,
                snippets: $snippets,
                isFirstAgentTurn: $case->isFirstAgentTurn,
            );
        });

        $completions = Http::recorded()
            ->map(static fn (array $exchange): Request => $exchange[0])
            ->filter(static fn (Request $request): bool => str_contains($request->url(), 'api.anthropic.com'));

        return new AgentEvalRun(
            draft: $draft,
            modelWasReached: $completions->isNotEmpty(),
            requestBody: (string) json_encode($completions->last()?->data() ?? []),
        );
    }

    /**
     * The skill set a case's seed produces, for the assertions that are about
     * grounding rather than about text.
     */
    public static function skillsFor(AgentEvalCase $case, Business $business): AgentSkillSet
    {
        return Tenancy::actingAs((int) $business->id, function () use ($case, $business): AgentSkillSet {
            ($case->seed)($business);

            return app(AgentSkills::class)->forThread(Conversation::factory()->create());
        });
    }
}
