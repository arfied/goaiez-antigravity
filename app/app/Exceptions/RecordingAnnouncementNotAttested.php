<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when call recording would be switched on without a recorded operator
 * attestation that every caller hears the announcement first (4505).
 *
 * ⚠️ **THE MESSAGE IS WRITTEN FOR THE OPERATOR WHO JUST PRESSED SOMETHING**, and
 * it names the step rather than the rule: an exception that says "2104 forbids
 * this" sends somebody to read a decision log, and what they need is to upload
 * the clip, put it first on the number's configuration, and say so.
 *
 * ⛔ **AND IT NAMES THE COMMAND, NOT A PHP METHOD** (4514). It read *"record the
 * attestation through `RecordingAnnouncement::attest()`"* while that method had
 * no caller anywhere — so the sentence told an operator standing at an Ops
 * screen to invoke a class they cannot reach, about a switch that could
 * therefore never be turned on at all.
 *
 * ⛔ **AND IT IS A REFUSAL RATHER THAN A WARNING.** The thing on the other side
 * of this switch is a recorded call in a two-party-consent state, and the
 * penalty for one lands on the tenant rather than on us.
 */
final class RecordingAnnouncementNotAttested extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forSwitch(string $key): self
    {
        return new self(
            "`{$key}` cannot be turned on until an operator has attested the recording "
            .'announcement. Upload the pre-rendered announcement clip, make it the first '
            .'thing on the number\'s Calls configuration so every caller hears it before '
            .'they can speak, then run: php artisan voice:announcement-attestation record '
            .'--clip=<clip> --operator=<you>. Recording a call in a two-party-consent '
            .'state without that announcement is the tenant\'s liability, and the switch '
            .'is what this refusal protects.',
        );
    }

    public static function because(string $message): self
    {
        return new self($message);
    }
}
