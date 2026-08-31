<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Enums\MarketingCapability;
use App\Livewire\Admin\PlatformSettings;
use App\Services\Config\DefaultsRegistry;
use App\Services\Content\Publishing;
use App\Services\Voice\RecordingAnnouncementAttestation;
use InvalidArgumentException;

/**
 * The five registry rows whose only effect is to make a public claim — 11703.
 *
 * ## ⛔ WHAT WAS WRONG, AND IT WAS ONE PRESS
 *
 * {@see MarketingCapability::claimSwitches()} names five rows that turn part of
 * the signed-out site from absent to published. **Four of the five describe
 * things this application does not do**, and the fifth describes something it
 * half does. Until this class
 * existed, a press on the Ops settings toggle reached the write through
 * `authorize()`, `OperatedElsewhere::has()` (a three-key list, none of them
 * these), an is-it-a-boolean check that a `false` seed passes,
 * {@see Publishing::isSiteWriteSwitch()} (literally one key, so no second press)
 * and {@see DefaultsRegistry::set()} — **and nothing on that path is about the
 * capability.** One press, no confirmation, no precondition.
 *
 * ## ⛔ THIS DOES NOT CHECK THAT THE FEATURE EXISTS, AND NOTHING HERE CAN
 *
 * ⚠️ **AN EXISTENCE CHECK IS THE OBVIOUS BUILD AND IT IS WRONG ON THE MEMBER
 * THAT MATTERS MOST** (11704). `App\Livewire\Account\Inbox` exists, is routed and
 * works; `features.inbox` publishes *"every text, email, and call from the same
 * person, in one story"*, and the Inbox's only data source filters to
 * `OutreachChannel::Sms` in every query it makes. **So "does a class exist"
 * answers yes here and is wrong**, and it is the one an operator would flip,
 * because there is a screen called Inbox. A gate that is right about the four
 * total absences and wrong about the partial one is coverage pointed the
 * reassuring way.
 *
 * ⛔ **AND A REFUSAL WAS REFUSED.** A gate that declines all five declines
 * everything — {@see MarketingCapability}'s whole design is that a dark
 * capability turns on **without a deploy** — and it would be deleted by the first
 * person who wants to launch one, which is the worst possible half-life for a
 * compliance-shaped control.
 *
 * ## ✅ WHAT IT DOES INSTEAD: IT CHANGES THE QUESTION
 *
 * {@see Publishing::authoriseSiteWrites()}'s shape, and decision 220's before it:
 * a literal `true` this class cannot fabricate for itself, produced in exactly
 * one place, after a person has been shown what becomes public. **The switch asks
 * "do you want this row on"; the second screen asks "is it true, today, that this
 * product does this", in the words the manifest already wrote for that reader.**
 * A person asked to affirm *"every text, email and call from one person lands in
 * one thread"* is far likelier to notice it is false than one pressing a toggle
 * labelled Inbox.
 *
 * ⚠️ **THE ASYMMETRY IS `Publishing`'s AND FOR A DIFFERENT REASON.** There it is
 * *"a switch you cannot turn off in an incident is worse than the thing it was
 * protecting against"*. Here it is that **withdrawing a claim must never cost more
 * than making one**: a ceremony on the way back is a ceremony that keeps a false
 * statement published while somebody reads a confirmation.
 *
 * ⛔ **IT CLAIMS NO PROTECTION** (5888). It does not check the claim, it does not
 * record what was checked, and it cannot unsay a sentence to somebody who has
 * already read it. What it produces is a `registry_changes` row naming an actor
 * and a moment, which {@see DefaultsRegistry::set()} was already writing — the
 * change is which question that actor was answering.
 *
 * ## ⛔ AN ATTESTATION BELONGS HERE BY THE DISCRIMINATOR AND IS REFUSED ANYWAY
 *
 * ⛔ **THE DISCRIMINATOR AGREES AND IT IS NOT THE WHOLE QUESTION** (12091).
 * {@see Publishing::authoriseSiteWrites()} draws the line at 5884: a
 * confirmation of intent is right where *"both facts that make a site write real
 * are already checked by the machine"*, and an attestation is right where an
 * operator is **recording a statement about a fact outside this application**.
 * The headline three paragraphs up — *nothing here can check that the feature
 * exists* — is that condition verbatim, so on the discriminator these five sit
 * on the attestation side.
 *
 * ⛔ **AND THE FIELD THAT MAKES ONE EVIDENCE CANNOT BE FILLED TRUTHFULLY TODAY.**
 * {@see RecordingAnnouncementAttestation} is the shape, and
 * its first parameter is *"the published wording the operator saw, exactly as
 * published"*. **The operator is not shown the published wording.** Rendered off
 * the Ops screen and read as text, the second press shows them the manifest
 * `description` above the switch and {@see MarketingCapability::heading()} inside
 * the panel — for `features.inbox`, the four words *"One thread per human"*. What
 * actually becomes public is a paragraph on `/features`, a row label on
 * `/compare` and an answer on `/faq`, and **none of those three appears on the
 * panel at all.** An attestation built now would store the heading and call it
 * the wording: *a record that reads as evidence and proves nothing*, which is
 * 5884's own sentence arriving from the other side.
 *
 * ⚠️ **AND WHAT THAT CLASS STORES IS A VERSION TOKEN RATHER THAN THE WORDS** —
 * `RecordingAnnouncement::STATEMENT_VERSION`, the literal `v1`. It earns its
 * place because it **invalidates**: `attest()` throws on a stale version,
 * `current()` returns null, and `voice.enabled` stops. **There is no equivalent
 * gate here and a gate was already refused above**, because a control that
 * declines all five would be deleted by the first person who wants to launch
 * one. Without invalidation the token is pure record, and the record already
 * exists: `registry_changes` carries the key, the actor and the moment, and the
 * words are in this repository under version control.
 *
 * ⛔ **SO NOTHING IS BUILT, ON `CLAUDE.md`'s FIRST TIEBREAKER — LESS SUPPORT
 * SURFACE.** What is owed is not a column: it is that the panel shows the
 * sentences that become public, in the words that become public. **After that an
 * attestation has something true to name**; before it, one would be ceremony
 * over a heading.
 *
 * ✅ **HALF OF THAT IS NOW DONE AND IT IS THE SMALLER HALF — 12244.** The panel
 * *"promises a `/compare` row and a `/faq` answer for every claim switch, which
 * is true of `features.commerce` and of no other"* (12097's finding, and the
 * sentence this paragraph carried until now). It no longer promises them: it
 * renders {@see MarketingCapability::publishedSurfaces()}, which
 * `MarketingTest`'s *"a claim switch reaches the signed-out views the
 * confirmation panel says it does"* derives from every blade in the tree and
 * asserts in both directions.
 * ⛔ **WHAT IS STILL OWED IS THE WHOLE OF THE ATTESTATION'S PRECONDITION**: an
 * operator is now told **which pages** start saying it and is still not shown
 * **the words**. Naming the page is not quoting the paragraph, and the refusal
 * above stands unchanged.
 */
final readonly class MarketingClaims
{
    public function __construct(private DefaultsRegistry $registry) {}

    /**
     * Whether a registry key is one of the five claim switches.
     *
     * ⚠️ **IT EXISTS SO THAT NOTHING ELSE HAS TO SPELL THE KEYS**, which is
     * {@see Publishing::isSiteWriteSwitch()}'s reason one word wider: five keys
     * rather than one, derived from the enum rather than typed here, so a lint
     * over the literals still has two legitimate occupants and not seven.
     */
    public function isClaimSwitch(string $key): bool
    {
        return MarketingCapability::claimSwitchFor($key) instanceof MarketingCapability;
    }

    /**
     * What the signed-out site starts saying, for the screen that asks.
     */
    public function capabilityFor(string $key): ?MarketingCapability
    {
        return MarketingCapability::claimSwitchFor($key);
    }

    /**
     * Which signed-out pages start saying it, for the panel that asks.
     *
     * ⛔ **THE PANEL SAID ALL THREE FOR ALL FIVE AND IT IS TRUE OF ONE** (12244).
     * See {@see MarketingCapability::publishedSurfaces()} for the map and for
     * the lint that holds it against every blade in the tree.
     *
     * ⚠️ **AN EMPTY LIST IS THE HONEST ANSWER FOR A KEY THAT IS NOT A CLAIM**,
     * and the panel is not reachable for one — `$confirming` holds two kinds of
     * key and the site-write panel is the other. A default that invented three
     * page names for a site-write confirmation would be the same defect one
     * panel over.
     *
     * @return list<string>
     */
    public function publishedSurfacesFor(string $key): array
    {
        return MarketingCapability::claimSwitchFor($key)?->publishedSurfaces() ?? [];
    }

    /**
     * Publish a capability claim — the only narrowing of the literal `true`.
     *
     * ⛔ **PHP'S LITERAL `true` TYPE IS THE GUARD, NOT AN `if`** (285, 5884). A
     * caller holding a plain `bool` **cannot call this at all**; they have to
     * narrow it first, which is the moment they must actually check that a person
     * answered the question. The failure is a `TypeError` from PHP and an error
     * from Larastan, with no code of ours involved.
     *
     * ⚠️ **AND IT IS NOT A SECOND LAYER OVER {@see DefaultsRegistry::set()}**,
     * which is public and takes `mixed`. What makes this the only door is the same
     * thing that makes it the only door for `actuation.enabled`: the five keys are
     * spelled in `MarketingCapability` and `DefaultsManifest` and nowhere else in
     * `app/`, and `MarketingTest` fails the build on a third speller.
     *
     * @throws InvalidArgumentException when the key is not a claim switch — a
     *                                  crafted Livewire payload reaching
     *                                  {@see PlatformSettings::confirmClaim()}
     *                                  must not become a general registry writer
     */
    public function publish(string $actor, string $key, true $affirmed): void
    {
        $this->refuseAKeyThatIsNotAClaim($key);

        $this->registry->set($key, $affirmed, $actor);
    }

    /**
     * Stop saying it — one press, deliberately.
     */
    public function withdraw(string $actor, string $key): void
    {
        $this->refuseAKeyThatIsNotAClaim($key);

        $this->registry->set($key, false, $actor);
    }

    private function refuseAKeyThatIsNotAClaim(string $key): void
    {
        if ($this->isClaimSwitch($key)) {
            return;
        }

        throw new InvalidArgumentException(
            "`{$key}` is not one of the signed-out site's capability claim switches, and this "
            .'is the only door for those. Every other registry row is written through '
            .'`DefaultsRegistry::set()`.'
        );
    }
}
