<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when `ReviewReplies::recordSuggestion()` refuses a draft on the
 * guardrails, as distinct from every other reason it refuses a draft (1854).
 *
 * ⚠️ **IT EXISTS SO THAT ONE CALLER CAN RECOVER AND THE OTHERS CANNOT.**
 * `recordSuggestion()` throws `InvalidArgumentException` for a first-party
 * review, another tenant's review, and a review that already carries somebody's
 * decision. `GenerateReplyJob` must re-file the safe template when the
 * *guardrails* refuse — otherwise a shop called *Gift* plus a model writing
 * *"enjoy your {{business_name}} card"* leaves the review with no reply row at
 * all, an `AutomationRun` with `output = null`, and a claim that hands itself
 * back to fail again forever. Catching the whole family instead would swallow
 * "this review already has an approved reply", which is a decision somebody
 * took and must not be papered over with a canned template.
 *
 * ⚠️ **It extends `InvalidArgumentException` deliberately.** The refusal is
 * still "this argument may not be written", every existing caller and test that
 * expects that type keeps working, and nothing has to learn a second exception
 * to keep failing closed.
 */
final class ReplyGuardrailRefused extends InvalidArgumentException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function because(string $message): self
    {
        return new self($message);
    }
}
