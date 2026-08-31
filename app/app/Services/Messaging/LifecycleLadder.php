<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\LifecycleRung;
use App\Exceptions\MessageCannotBeComposed;
use App\Services\Messaging\Composer\ComposedText;
use App\Services\Messaging\Composer\SmsBudget;
use App\Support\LegalCanon;
use App\Support\Messaging\LifecycleLadderCatalog;

/**
 * Compose one rung of the lifecycle ladder — CC-5 §2.
 *
 * ## ⛔ "NOTHING CALLS THIS ON A SCHEDULE" WAS TRUE AND IS HALF TRUE —
 * CORRECTED 2026-08-25 (9464)
 *
 * CC-5 §2 asks for the strings, {@see LifecycleLadderCatalog} is the strings,
 * and this is what turns one into a message and measures it.
 *
 * ⛔ **THE SUPERSEDED READING IS KEPT AND DATED RATHER THAN DELETED** (4368),
 * because three live artefacts quote it word for word — `TrialReminders`' own
 * docblock, decision 5258 and decision 9395 — and deleting it would leave three
 * quotations pointing at nothing: *"⛔ NOTHING CALLS THIS ON A SCHEDULE, AND
 * THAT IS SAID OUT LOUD. There is no 75/90/100 notifier and no trial rung sender
 * anywhere in `app/`; `RenewalReminders` is a statutory renewal notice for an
 * account that has already bought, and it is a different message to a different
 * person. **The dispatcher is a slice of its own** — decision 5258 records it
 * as owed, because `CLAUDE.md`'s first recurring failure is a thing with no
 * writer, and the honest way to carry that is to name it rather than to
 * half-build a sender that texts a real person."*
 *
 * ⚠️ **THE TREE AS IT STANDS.** `App\Services\Billing\TrialReminders::remind()`
 * calls {@see self::email()}; `SendTrialReminders` sweeps every owner and
 * `routes/console.php` runs it daily at 05:45 (9395). ⛔ **The three usage rungs
 * — 75/90/100 — still have no sender of any kind, and that is deliberate**:
 * they are a different clock, credits consumed rather than days elapsed, and
 * 9395 scoped the dispatcher to the trial set on purpose. ⚠️ **So the absence
 * is now half an absence**, which is the state the old sentence was least able
 * to describe: the surviving half reads as considered because the sentence
 * carrying it was right about everything else.
 *
 * ## ⛔ `text()` HAS NO CALLER AT ALL, AND IT MAY NOT BE TAGGED `@uncalled`
 *
 * ⛔ **THIS SAID "THREE RUNGS CARRY AN AUTHORED SMS LINE" AND SIX DO —
 * CORRECTED 2026-08-28 (10942).** Three is the count of the **trial** set,
 * which is `App\Services\Billing\TrialReminders`' scope and not this class's:
 * this composes every rung, {@see LifecycleRung::hasText()} is the answer, and
 * `TrialDaySeven` is the only case it refuses. 10548 names *"the six … SMS
 * rungs"* as owed — and this same docblock says *"four of the six
 * carry an em dash"* four paragraphs below, so **one comment held two counts of
 * one set and neither was checked by anything.** ⚠️ **The count is stated as a
 * predicate now rather than as a number**, and
 * `tests/Feature/Billing/OwnerChannelGrantTest.php` pins both the cardinality
 * and the identity of the one silent rung, so the sentence cannot go stale
 * again without reddening.
 *
 * **Nothing sends one, and that is a refusal rather than a deferral** (9399) —
 * ⛔ **but the first of its three reasons is superseded, and is kept dated
 * rather than deleted** (4368). 9399 wrote *"a text to an account holder spends
 * the tenant's own granted balance"*; **10546 ruled the opposite** and the owner
 * channel built on that ruling debits nothing
 * (`App\Services\Sms\PlatformTexter::sendToOwner()`). What survives, and is
 * what actually holds this method callerless: the wording an owner agreed to
 * (`App\Services\Consent\OwnerNotifyDisclosure::TEXT`) discloses account
 * notices and not purchase offers, and **five** of these six rungs say *"add a
 * card"* or *"add your card now"*; the complaint rate still lands on our own 10DLC registration (2101);
 * and the only ladder clock this application runs fires at 05:45, inside
 * federal quiet hours. **`TrialReminders`' own docblock carries the long form —
 * this class does not restate it, because two copies of one rule is the defect
 * this correction exists to close.**
 *
 * ⛔ **`@uncalled` IS THIS CODEBASE'S MECHANISM FOR EXACTLY THIS AND IT CANNOT
 * HOLD THIS DECLARATION — MEASURED 2026-08-25 RATHER THAN REASONED** (9465).
 * Planting the tag here fails the build with *"STALE — SOMETHING CALLS IT NOW"*
 * about a method nothing calls, under a message telling the reader to delete the
 * tag and not the caller. `ops:method-callers` scores `text()` alive from the
 * one untyped receiver in the tree — `$event->message->text(…)` at
 * `PlatformMailContext.php:182`, which is Symfony's `Email::text()` and no
 * declaration in `app/` at all — and `--collisions`, the arm that exists to
 * disclose that shortfall, skips the name because it needs **two** declarations
 * alive on untyped evidence alone and this name has one.
 *
 * ## ⛔ IT REFUSES WHERE THE GUARANTEE IS UNSET AND MEASURES WHERE IT IS LONG
 *
 * Two rungs carry R39's written promise and neither carries its words: they
 * carry `{guarantee}`, resolved from `legal.guarantee_sentence`. With that key
 * empty **those two rungs do not compose at all**, which is the fail-closed
 * direction — a rung that quietly dropped the sentence would withdraw a
 * commitment from somebody who had been told it applied, and nothing on any
 * screen would say so.
 *
 * ## ⚠️ IT MEASURES AND DOES NOT REFUSE ON LENGTH — `ReviewInviteSender`'s RULE
 *
 * The two composers in this application answer length differently on purpose,
 * and the dividing line is *whose wording it is and what refusing costs*.
 * `ReactComposer` refuses an over-budget campaign, because the tenant can
 * shorten their own template and the alternative is a marketing message nobody
 * reads. `ReviewInviteSender::compose()` deliberately does not measure, because
 * refusing would drop a message somebody was promised over a fraction of a cent.
 * **These rungs are the second kind**: a person is told their service is about
 * to pause, and refusing to tell them because the line runs to two segments is
 * the hard-fail rule 43's surviving half forbids (3294). So the measurement
 * travels with the text and the caller decides.
 *
 * ⚠️ **AND THE MEASUREMENT HAS SOMETHING TO SAY.** R32 heads its set *"SMS lines
 * GSM-clean, ≤159 incl. {Link}"* and four of the six carry an em dash, which is
 * in neither GSM 03.38 table — so those four are UCS-2 with a 70-unit budget.
 * The character is not substituted here; see the catalogue for why that is the
 * owner's call and not this lane's.
 */
final class LifecycleLadder
{
    public function __construct(
        private readonly LegalCanon $canon,
        private readonly SmsBudget $budget,
    ) {}

    /**
     * One rung's subject line, with its guarantee insert where it has one.
     *
     * @return array{subject: string, insert: ?string}
     *
     * @throws MessageCannotBeComposed
     */
    public function email(LifecycleRung $rung, string $businessName, ?string $contactName, string $link, ?string $date = null): array
    {
        $copy = LifecycleLadderCatalog::for($rung);

        return [
            'subject' => $this->fill($copy['subject'], $rung, $businessName, $contactName, $link, $date),
            'insert' => $copy['email_insert'] === null
                ? null
                : $this->fill($copy['email_insert'], $rung, $businessName, $contactName, $link, $date),
        ];
    }

    /**
     * One rung's text message, measured.
     *
     * @throws MessageCannotBeComposed when the rung has no text, a slot cannot
     *                                 be filled, or the guarantee is unset
     */
    public function text(LifecycleRung $rung, string $businessName, ?string $contactName, string $link, ?string $date = null): ComposedText
    {
        $copy = LifecycleLadderCatalog::for($rung);

        if ($copy['text'] === null) {
            throw MessageCannotBeComposed::because(sprintf(
                'The lifecycle rung `%s` is email only — R32\'s channel law is one message on one '
                .'channel a day, and no text was authored for this beat. Writing one here would be '
                .'this application inventing a text message to a member of the public.',
                $rung->value,
            ));
        }

        $body = $this->fill($copy['text'], $rung, $businessName, $contactName, $link, $date);

        return new ComposedText($body, $this->budget->encodingOf($body), $this->budget->lengthOf($body));
    }

    /**
     * Substitute every slot, and refuse rather than leave one showing.
     *
     * ⚠️ **THE SLOT-RESOLUTION GUARD AT THIS RENDER POINT** (CC-5 §0). The
     * closing check is the same claim `ReactComposer::assertNothingUnsubstituted()`
     * and `ReviewAskComposer::render()` each make at theirs — three render
     * points, three guards, none of them standing in for another, because 398's
     * shape is an outer guard that makes an inner one unfalsifiable.
     *
     * @throws MessageCannotBeComposed
     */
    private function fill(
        string $template,
        LifecycleRung $rung,
        string $businessName,
        ?string $contactName,
        string $link,
        ?string $date,
    ): string {
        $business = trim($businessName);

        if ($business === '' && str_contains($template, '{business}')) {
            throw MessageCannotBeComposed::because(
                "The lifecycle rung `{$rung->value}` names the business and this account has no "
                .'name to use.'
            );
        }

        if ($date === null && str_contains($template, '{date}')) {
            throw MessageCannotBeComposed::because(sprintf(
                'The lifecycle rung `%s` states a date and none was given. A message telling '
                .'somebody when their service pauses, with the date missing, is worse than no '
                .'message: it is the same sentence with the one fact removed.',
                $rung->value,
            ));
        }

        if ($contactName === null && str_contains($template, '{name}')) {
            throw MessageCannotBeComposed::because(sprintf(
                'The lifecycle rung `%s` greets the account holder by name and none is on record.',
                $rung->value,
            ));
        }

        $filled = str_replace(
            ['{business}', '{name}', '{link}', '{date}', '{guarantee}'],
            [
                $business,
                $contactName ?? '',
                $link,
                $date ?? '',
                // ⛔ **READ ONLY WHEN THE TEMPLATE ASKS FOR IT.** Calling
                // `guaranteeSentence()` unconditionally would refuse every rung
                // on an installation where the key is unset — including the
                // 100% pause notice, which is the one message a person most
                // needs to receive.
                str_contains($template, '{guarantee}') ? $this->canon->guaranteeSentence() : '',
            ],
            $template,
        );

        if (preg_match('/\{[^}]*\}|\[[^\]]*\]/u', $filled, $matches) === 1) {
            throw MessageCannotBeComposed::because(sprintf(
                'The lifecycle rung `%s` still contains %s after substitution. Only %s are '
                .'substituted, spelled exactly like that; anything else is delivered to the '
                .'recipient with its brackets showing.',
                $rung->value,
                $matches[0],
                implode(', ', LifecycleLadderCatalog::SLOTS),
            ));
        }

        return $filled;
    }
}
