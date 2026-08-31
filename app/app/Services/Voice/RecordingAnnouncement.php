<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Exceptions\RecordingAnnouncementNotAttested;
use App\Jobs\Voice\IngestVoiceEventJob;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Support\Carbon;

/**
 * Whether an operator has recorded that every caller hears the announcement —
 * and the only thing that lets call recording be switched on (4505).
 *
 * ## ⛔ WHAT THIS REPLACES IS A DOCBLOCK, AND THE DOCBLOCK WAS THE FINDING
 *
 * {@see VoiceGreeting} was introduced as *"the mechanism"* for 2104's
 * unconditional recording announcement, and **it had no caller anywhere in
 * `app/`**: a value object nobody constructs enforces nothing, which is
 * `CLAUDE.md`'s 314–316 landing inside the slice that quotes it. Meanwhile the
 * owner's own screen printed *"every call we pick up is recorded, and every
 * caller is told so before they can speak"* as a fact, unconditionally — so an
 * operator who skipped step 5 of the activation runbook (upload the clip,
 * configure it on the number) produced a **California §632 violation the
 * tenant's own page had told them could not happen**, with the penalty landing
 * on the tenant.
 *
 * ## What is actually enforced, stated exactly
 *
 * ⛔ **THIS APPLICATION STILL DOES NOT PLAY THE AUDIO**, and no amount of code
 * here can. The greeting is configured on Infobip's own number setup, which is
 * outside this repository — so what can be enforced is that **the switch which
 * causes recordings to be kept cannot be moved without a person recording, in a
 * form that survives them leaving, that they configured it and which clip they
 * used.** Two layers carry that:
 *
 *   1. {@see DefaultsRegistry::set()} refuses to turn `voice.enabled` on
 *      without a current attestation.
 *   2. {@see IngestVoiceEventJob} asks {@see self::isAttested()} again before a
 *      recording event is acted on, because a hand-edited `platform_settings`
 *      row reaches the switch without passing the first layer.
 *
 * ⚠️ **AND WHAT IT DOES NOT COVER IS SAID RATHER THAN GLOSSED**, or this class
 * becomes the docblock it replaced. An operator can attest falsely; nothing here
 * dials the number to check. What it buys is that the claim is *made*, by a
 * named actor, against a named clip, in wording we can produce — which is the
 * difference between a step somebody forgot and a statement somebody made.
 * Attestation is also **platform-wide, not per tenant**: the announcement rides
 * the platform's own number configuration, so one operator's statement covers
 * every tenant answered by it, and a per-tenant one would be a control no tenant
 * can act on.
 *
 * ## Why the record is a registry key rather than a new table
 *
 * `registry_changes` is already append-only, already platform-scoped, already
 * carries the actor and both sides of every change, and is already the one
 * history `DefaultsRegistry` writes. A `voice_recording_attestations` table
 * would duplicate all of that plus an RLS exemption argument, to hold one row
 * that changes when a clip changes. **The evidence property comes from the
 * change log, not from the settings row** — the row is current state and the log
 * is what was claimed, when, and by whom.
 */
final class RecordingAnnouncement
{
    /** Where the current attestation is stored. */
    public const string SETTING_KEY = 'voice.recording_announcement_attestation';

    /** The switch it gates. */
    public const string SWITCH_KEY = 'voice.enabled';

    /**
     * The published wording, versioned.
     *
     * ⚠️ **BUMP THIS WHEN THE WORDS CHANGE, AND AN OLD ATTESTATION STOPS
     * COUNTING** — `ConsentCapture`'s rule, and the reason `ImportAttestation`
     * refuses a padded version. An attestation is a statement about *these*
     * words; carrying it forward onto different ones is how a record stops being
     * evidence of anything.
     */
    public const string STATEMENT_VERSION = 'v1';

    public function __construct(private readonly DefaultsRegistry $registry) {}

    /**
     * The words an operator is shown and agrees to.
     *
     * Here rather than in a screen or a command, for `CallRoutingMode::description()`'s
     * reason: the vocabulary stays closed, one test asserts all of it, and what
     * is stored is exactly what was displayed.
     */
    public function statement(): string
    {
        return 'I have uploaded the recording announcement clip and made it the first thing '
            .'played on this number, before a caller can speak, on every call this platform '
            .'answers. Callers hear: "'.VoiceGreeting::ANNOUNCEMENT.'"';
    }

    /**
     * Record one operator's attestation.
     *
     * ⛔ **THE GREETING IS COMPOSED HERE, AND THAT IS THE CHECK RATHER THAN A
     * FORMALITY.** {@see VoiceGreeting::forRecordedCall()} is the only way to
     * describe a greeting in this application and it cannot describe one whose
     * announcement is missing or blank — so composing the attested clip through
     * it is what turns *"they typed something"* into *"the thing they named can
     * actually be the first item of a greeting"*. A blank or whitespace clip id
     * throws out of the value object below rather than being stored as evidence.
     */
    public function attest(RecordingAnnouncementAttestation $attestation): RecordingAnnouncementAttestation
    {
        if ($attestation->statementVersion !== self::STATEMENT_VERSION) {
            throw RecordingAnnouncementNotAttested::because(
                'That attestation is against wording version `'.$attestation->statementVersion
                .'`, and the current statement is `'.self::STATEMENT_VERSION.'`. Show the '
                .'operator the words that are published now and record their answer to those.',
            );
        }

        $greeting = VoiceGreeting::forRecordedCall($attestation->announcementMediaId);

        if ($greeting->playlist[0] !== trim($attestation->announcementMediaId)) {
            // Unreachable while `forRecordedCall()` puts the announcement first,
            // which is its whole invariant — asserted rather than assumed,
            // because this is the claim the attestation is evidence of.
            throw RecordingAnnouncementNotAttested::because(
                'The announcement clip did not compose as the first thing a caller hears.',
            );
        }

        $this->registry->set(
            self::SETTING_KEY,
            $attestation->toArray(Carbon::now()->toIso8601String()),
            $attestation->attestedBy,
        );

        return $attestation;
    }

    /**
     * Withdraw the attestation — the clip changed, or nobody can now say what a
     * caller hears.
     *
     * ⛔ **AN EMPTY BLOB RATHER THAN A NULL, AND THAT IS THE COLUMN'S DOING.**
     * `platform_settings.value` is NOT NULL and a JSON null falls back to the
     * manifest seed, so "withdrawn" has to be a value that *reconstructs to
     * nothing* rather than an absence. `[]` does, through
     * {@see RecordingAnnouncementAttestation::fromStored()}.
     *
     * ⚠️ **IT DOES NOT TURN THE SWITCH OFF, DELIBERATELY.** Withdrawing the
     * attestation stops a recording being stored ({@see IngestVoiceEventJob})
     * and stops `voice.enabled` being turned *on* again, and an operator who
     * also wants calls to stop being recorded turns the switch off themselves —
     * two acts, both logged, rather than one that quietly does the other.
     */
    public function revoke(string $actor): void
    {
        $this->registry->set(self::SETTING_KEY, [], $actor);
    }

    /**
     * The attestation in force, or null when there is none.
     */
    public function current(): ?RecordingAnnouncementAttestation
    {
        $stored = $this->registry->value(self::SETTING_KEY);

        $attestation = RecordingAnnouncementAttestation::fromStored($stored);

        if ($attestation === null) {
            return null;
        }

        // ⚠️ **A SUPERSEDED WORDING IS NOT AN ATTESTATION**, for the reason
        // `STATEMENT_VERSION` gives. This is the read where that bites, and it
        // fails in the direction that stops recordings rather than the one that
        // keeps them.
        return $attestation->statementVersion === self::STATEMENT_VERSION ? $attestation : null;
    }

    public function isAttested(): bool
    {
        return $this->current() !== null;
    }

    /**
     * The greeting a recorded call would play, or null when nobody has attested.
     *
     * @param  string|null  $businessMediaId  The tenant's own greeting clip, when
     *                                        they have one. Null is ordinary.
     */
    public function greeting(?string $businessMediaId = null): ?VoiceGreeting
    {
        $attestation = $this->current();

        return $attestation === null
            ? null
            : VoiceGreeting::forRecordedCall($attestation->announcementMediaId, $businessMediaId);
    }
}
