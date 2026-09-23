<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Services\Reviews\ResponseTemplates;
use RuntimeException;

/**
 * Something was asked of an example reply that {@see ResponseTemplates} will
 * not do.
 *
 * ⚠️ **A REFUSAL HERE IS A PROGRAMMING ERROR, NOT A CONDITION TO RECOVER FROM**
 * — `GrowthPageRefused`'s rule. The owner-facing panel validates blankness,
 * length and the cap before it calls, and asks the guardrail question through
 * this class so that it can turn the answer into a field-level message. These
 * exist for the *second* caller, which is what a chokepoint is for.
 *
 * ⚠️ **ONE CLASS RATHER THAN A `RuntimeException` PER CASE, AND THE MESSAGES
 * ARE FOR A DEVELOPER RATHER THAN AN OWNER.** Nothing here is rendered to a
 * tenant: the panel writes its own copy in outcome language, because
 * *"guardrailed"* is internal vocabulary and *"that example offers something we
 * cannot promise on your behalf"* is what a person can act on.
 */
final class ResponseTemplateRefused extends RuntimeException
{
    public static function blank(): self
    {
        return new self(
            'Refusing to store an example reply with a blank name or a blank body. An empty body would be '
            .'interpolated into every reply prompt as an empty bullet, which teaches the model nothing and '
            .'costs a token budget on every draft for the life of the account.',
        );
    }

    public static function tooLong(): self
    {
        return new self(sprintf(
            'Refusing to store an example reply longer than its ceiling (%d characters of name, %d of body). '
            .'Three bodies cross the wire on every reply draft, so the ceiling is paid on every AI call this '
            .'tenant ever makes — and what is being exemplified is a two-to-four-sentence Google reply.',
            app(ResponseTemplates::class)->maxNameLength(),
            app(ResponseTemplates::class)->maxBodyLength(),
        ));
    }

    public static function full(): self
    {
        return new self(sprintf(
            'Refusing to store a %dth example reply. ResponseTemplates::examples() takes the %d oldest, so '
            .'anything beyond that would be stored, listed on the owner\'s screen and read by nothing — a '
            .'control that appears to work and does not.',
            ResponseTemplates::MAX_TEMPLATES + 1,
            ResponseTemplates::MAX_TEMPLATES,
        ));
    }

    public static function guardrailed(): self
    {
        return new self(
            'Refusing to store an example reply that ReplyGuardrails::allows() rejects. The body is a '
            .'few-shot example for a prompt whose output publishes under the business\'s name on their '
            .'public Google listing, and a body offering a refund, compensation or a legal position teaches '
            .'the model to draft one. The guardrail at the generator would then refuse every draft this '
            .'example touched, silently, with the owner seeing only the canned template — 1735\'s failure '
            .'reached through a door the owner opened themselves.',
        );
    }

    public static function missing(int $id): self
    {
        return new self(sprintf(
            'Example reply [%d] is not this tenant\'s, or no longer exists. The global scope and row-level '
            .'security both answer that question, and neither distinguishes "deleted" from "somebody '
            .'else\'s" on purpose — telling them apart would confirm the existence of another tenant\'s row.',
            $id,
        ));
    }
}
