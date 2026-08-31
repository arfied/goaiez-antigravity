<?php

declare(strict_types=1);

namespace Tests\Support\AgentEvals;

use App\Services\Agent\AgentReplyDraft;

/**
 * What one arm of one eval produced.
 *
 * ⛔ **`$modelWasReached` IS NOT DIAGNOSTIC COLOUR — IT IS THE GUARD AGAINST THE
 * WHOLE SUITE PASSING FOR THE WRONG REASON.** `AiRouter` refuses with
 * `credential_not_configured` *before* it reaches the faked wire, and a refusal
 * degrades to rail 6's static template — which contains no forbidden phrase, no
 * figure and no link. So a misconfigured fixture makes **every** refusal eval pass
 * while proving nothing at all, and the failure looks exactly like success.
 * `AgentGroundingTest` paid for that lesson and `AgentComposerTest`'s header warns
 * about it; here it is carried on the result object so that no case can forget to
 * ask.
 *
 * ⚠️ **AND THE COMPLIANT ARM NEEDS IT JUST AS MUCH.** A compliant arm that never
 * reached the model would return the template, which is also not a refusal — so it
 * would pass its own assertion too. Both arms assert it.
 */
final readonly class AgentEvalRun
{
    public function __construct(
        public AgentReplyDraft $draft,
        public bool $modelWasReached,
        /**
         * The JSON body of the completion request, for the cases whose property is
         * about what the model was *given* rather than what it said back. Empty
         * when no request was made.
         */
        public string $requestBody,
    ) {}
}
