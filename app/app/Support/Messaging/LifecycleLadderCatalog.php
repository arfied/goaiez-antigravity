<?php

declare(strict_types=1);

namespace App\Support\Messaging;

use App\Enums\LifecycleRung;
use App\Services\Messaging\LifecycleLadder;
use App\Support\LegalCanon;
use RuntimeException;

/**
 * THE LIFECYCLE LADDER's WORDS — R32's usage and trial rungs, with PIII-29 D7
 * and PIII-30's landing (CC-5 §2).
 *
 * ## ⚠️ WHERE THIS LIVES, AND WHY IT IS NOT A LANG FILE OR A TABLE
 *
 * CC-5 §2 says to seed these *"into wherever trial/credit notifications already
 * source their copy (lang keys or a content table — match the house)"*. **There
 * is no such place.** `lang/en/` holds exactly one file, for the hosted feedback
 * page, and its own docblock explains why nothing else in the application is
 * translated. ⛔ **THIS READ "No trial or credit notification exists at all —
 * no 75/90/100 notifier, no day-10 or day-13 rung sender" AND THE SECOND HALF
 * STOPPED BEING TRUE ON 2026-08-25 — CORRECTED (9464).** The four trial rungs
 * have a sender; the three usage rungs do not. ⚠️ **The argument this clause
 * exists for is untouched and is why the clause stays**: what it is reaching for
 * is that there is no lang file and no content table to seed into, and building
 * the sender created neither — `TrialReminders` reads this catalogue through
 * {@see LifecycleLadder} like everything else. `RenewalReminders` remains a
 * statutory renewal notice for an account that has already bought.
 *
 * So the house shape this matches is the other one it already has: a reviewed
 * catalogue in `app/Support` beside `LegalDraftManifest`, `PlanOfferCatalog`,
 * `CampaignPackCatalog` and `SupportMacroCatalog`. No table, because there is no
 * per-tenant activation and nothing to edit through Ops — a fifth platform table
 * whose only reader is a composer would be surface with no purpose.
 *
 * ## ⛔ THE TRIAL RUNGS ARE DISPATCHED AND THE USAGE RUNGS ARE NOT — CORRECTED
 * 2026-08-25 (9464)
 *
 * {@see LifecycleLadder} composes a rung and measures it. **A scheduler picks
 * the four trial rungs**: `App\Console\Commands\SendTrialReminders`, daily at
 * 05:45 in `routes/console.php`, through
 * `App\Services\Billing\TrialReminders::remind()` (9395). ⛔ **Nothing
 * dispatches the three usage rungs, nothing sends any rung's SMS line, and no
 * screen shows a rung at all** — the first deliberately, because the usage
 * ladder is a different clock; the second refused in writing (9399); the third
 * simply unbuilt.
 *
 * ⛔ **THE SUPERSEDED READING, KEPT AND DATED** (4368) — `TrialReminders`'
 * docblock quotes it word for word and decision 5258 is where it came from, so
 * deleting it would strand both: *"⛔ NOTHING DISPATCHES THIS LADDER, AND THAT
 * IS STATED RATHER THAN IMPLIED. {@see LifecycleLadder} composes a rung and
 * measures it. **No scheduler picks a rung, no job sends one, and no screen
 * shows one.** CC-5 §2 asks for the strings and this is the strings; the sender
 * is a slice of its own, and writing a half of one here would be `CLAUDE.md`'s
 * first recurring failure with a real text message on the end of it. Decision
 * 5258 records what is owed."*
 *
 * ⚠️ **AND HOW IT WENT STALE IS THE FINDING RATHER THAN THE STALENESS.**
 * `CLAUDE.md` holds this paragraph up by name as the model to copy — *"a
 * deliberate absence that is written AT THE DECLARATION is the one form of this
 * shape that costs the next lane nothing … the lane that built the sender spent
 * one grep establishing it"* — and the lane that built the sender did read it,
 * did quote it, and left it false. **A note at a declaration is read by whoever
 * opens the file, which is the person who needs it and never the person who
 * invalidates it.** Nothing reddened: no column lost a writer, no method lost a
 * caller, and the suite stayed green through the merge that falsified this.
 *
 * ## ⛔ R32 CALLS ITS SMS LINES "GSM-CLEAN" AND FOUR OF THEM ARE NOT
 *
 * R32's header: *"SMS lines GSM-clean, ≤159 incl. {Link}"*. ⛔ **FOUR OF THE SIX
 * CARRIED AN EM DASH AND TWO STILL DO — CORRECTED 2026-08-29 (12281).** The dash
 * is in neither the GSM 03.38 basic alphabet nor its extension table, so those
 * messages are submitted as UCS-2 and their budget is **70 units, not 159**.
 * ⚠️ **The two that were fixed were not fixed for this reason**: the three usage
 * rungs were rewritten as account notices rather than purchase offers, and the
 * dashes left with the offers. The two trial rungs still carry one. `SmsBudget`
 * proves it, and `tests/Feature/Messaging/MessageCanonTest.php`'s *"R32 calls its
 * SMS lines GSM-clean and two of the six are still not"* names them. ⛔ **THIS SAID
 * `LifecycleLadderTest` AND NO FILE OF THAT NAME HAS EVER EXISTED IN THIS
 * REPOSITORY — CORRECTED 2026-08-25 (9466).** It was written here on the day
 * this catalogue shipped and the tests landed under another name; two further
 * references to it survive in `app/Enums/LifecycleRung.php`, which no lane held
 * this wave. **A docblock naming the instrument that would have caught a thing
 * is what stops the next reader looking for the instrument.**
 *
 * ⛔ **THEY ARE SEEDED VERBATIM ANYWAY AND THE DASH IS NOT SUBSTITUTED.**
 * `SmsBudget`'s own docblock refuses that in as many words — *"doing it here
 * would mean this class silently rewriting somebody's message to make its own
 * answer come out right … the substitution belongs to whoever owns the wording"*
 * — and the owner owns this wording. What this lane does is **measure and say
 * so**; swapping `—` for `-` is one character of somebody else's copy and one
 * sentence in a reply, and it is theirs to make.
 *
 * ## ⚠️ THE GUARANTEE IS A SLOT, ON TWO RUNGS, AND ITS WORDS ARE NOT HERE
 *
 * The guarantee rider writes one wording and PIII-29 D7 writes another. Pasting either
 * would make this file the third source of a promise that has to have one, so
 * both rungs carry `{guarantee}` and {@see LegalCanon} resolves it. Until
 * `legal.guarantee_sentence` is set, those two rungs refuse to compose —
 * fail-closed, because a rung that quietly dropped the promise would withdraw a
 * commitment from somebody who was told it applied.
 */
final class LifecycleLadderCatalog
{
    /**
     * The placeholders a rung may carry.
     *
     * `MessageSlots::PLATFORM` plus the canon slot. `{guarantee}` is not in
     * `PLATFORM` because it is not a merge field: nobody passes it in, and the
     * only thing that may fill it is the registry.
     *
     * @var list<string>
     */
    public const array SLOTS = [...MessageSlots::PLATFORM, '{guarantee}'];

    /**
     * One rung's authored copy.
     *
     * ⚠️ **THREE FIELDS, BECAUSE THE GUARANTEE RIDER NAMES THREE.** It gives the expiry
     * rung an *"Email insert"* and an *"SMS variant"* separately, so a rung is a
     * subject, a text line where the channel law allows one, and the insert that
     * carries the promise where the rung carries one.
     *
     * @return array{subject: string, text: ?string, email_insert: ?string}
     *
     * @throws RuntimeException when a rung has no copy, or copy no rung wants
     */
    public static function for(LifecycleRung $rung): array
    {
        $copy = self::declared()[$rung->value] ?? null;

        if ($copy === null) {
            throw new RuntimeException(
                "The lifecycle rung `{$rung->value}` has no authored copy. A rung that rendered an "
                .'empty message would be a notification a person receives with nothing in it, and '
                .'the enum is deliberately the key set so that this is loud rather than blank.'
            );
        }

        if ($rung->hasText() && $copy['text'] === null) {
            throw new RuntimeException(
                "The lifecycle rung `{$rung->value}` says it has a text and none is authored."
            );
        }

        if (! $rung->hasText() && $copy['text'] !== null) {
            throw new RuntimeException(
                "The lifecycle rung `{$rung->value}` is email-only and carries an authored text. "
                .'R32\'s channel law is one message on one channel a day; an unreachable SMS line '
                .'is one somebody will wire up later without reading why it was not sent.'
            );
        }

        // ⛔ **THE PREDICATE AND THE COPY MUST AGREE, IN BOTH DIRECTIONS.** A
        // rung that says it carries the guarantee and has no insert drops a
        // written promise silently; one that has an insert and says it does not
        // sends a promise on a rung the guarantee policy says does not change. Neither is
        // visible on a screen, so both are refused here.
        if ($rung->carriesTheGuarantee() !== ($copy['email_insert'] !== null)) {
            throw new RuntimeException(
                "The lifecycle rung `{$rung->value}` and its authored copy disagree about the written "
                .'guarantee. `LifecycleRung::carriesTheGuarantee()` is the ruling — the guarantee policy states: "the '
                .'trial-expiry rung gains the guarantee line immediately before the CTA. No other '
                .'rung changes" — and the copy has to match it.'
            );
        }

        foreach (array_filter([$copy['subject'], $copy['text'], $copy['email_insert']]) as $string) {
            MessageSlots::assertOnly($string, self::SLOTS, "The lifecycle rung `{$rung->value}`");
        }

        return $copy;
    }

    /**
     * R32 §USAGE LADDER and §TRIAL LADDER, plus PIII-29 D7 and PIII-30 D15.
     *
     * ⚠️ **`{Business}`, `{Name}`, `{Link}`, `[DATA]` AND `[LINK]` ARE ALL
     * TRANSLATED HERE AND NOWHERE ELSE** (CC-5 §0). The sources spell them five
     * ways across two documents; this application substitutes `{business}`,
     * `{name}`, `{link}` and `{date}`.
     *
     * @return array<string, array{subject: string, text: ?string, email_insert: ?string}>
     */
    private static function declared(): array
    {
        return [
            // ⛔ **THE THREE USAGE BEATS HAVE NO SENDER OF ANY KIND, ON EITHER
            // CHANNEL, AND THREE THINGS BLOCK THEM THAT ARE NOT "SOMEBODY HAS
            // TO WRITE THE JOB" — RECORDED HERE 2026-08-28 (10947), AT THE COPY,
            // BECAUSE THIS IS WHERE THE NEXT BUILDER WILL BE.**
            //
            //   1. **Nothing computes the trigger.** No percentage of any grant
            //      is computed anywhere in `app/`: every `CreditLedger::balance()`
            //      reader displays it, gates a spend or clamps a clawback, and
            //      `CreditProduct::monthlyGrantKey()` has one caller and it
            //      grants. The ingredients exist; the clock does not.
            //   2. ⛔ **"Credits" IS SINGULAR HERE AND THERE ARE SIX BALANCES.**
            //      3357 gave the ledger a `pool` and 3419 a `product` — SMS,
            //      email and AI × granted and top-up — so *"75% of this month's
            //      credits"* has no referent. Does a tenant at 75% of SMS and
            //      10% of email get one message or three? Does the never-expiring
            //      top-up pool count toward the percentage? **A product ruling,
            //      and a lane may not pick one.**
            //   3. ⛔ **`usage_100` STATES A BEHAVIOUR THIS APPLICATION DOES NOT
            //      IMPLEMENT.** *"Your GOAIEZ credits are used up and service is
            //      paused"* — an exhausted balance refuses **that product's**
            //      sends and degrades to `null` (2904, rule 43's surviving
            //      half); nothing pauses, and the other two products carry on.
            //      **Wiring the sender would ship a false statement to an
            //      account holder.**
            //
            // ⚠️ **THE COPY IS NOT CHANGED HERE AND MUST NOT BE**, on 9399's own
            // precedent for `trial_ended`'s tense mismatch: *"the copy is
            // the owner's … whoever wires the SMS half has to reconcile them with the
            // owner."* Same rule, same reason. ⛔ **And the email half is blocked
            // too**, which is the part a reader skims past: 10726 already makes
            // account-holder mail free and consent-free, so the channel is not
            // what stops these — **nothing knows when to send them.**
            LifecycleRung::UsageSeventyFive->value => [
                'subject' => "You're at 75% — here's how to never think about this again",
                'text' => "GOAIEZ: {business} has used 75% of this month's credits. All "
                    .'running normally. See usage: {link} Reply STOP to opt out.',
                'email_insert' => null,
            ],

            LifecycleRung::UsageNinety->value => [
                'subject' => '10% left — 30 seconds now saves a pause later',
                'text' => "GOAIEZ: you have used 90% of this month's credits. We will tell you "
                    .'if service pauses. See your usage: {link} Reply STOP to opt out.',
                'email_insert' => null,
            ],

            // ⛔ R48c's word law, in the one place it matters most: **paused**,
            // and never "expired". The credits are used up; the account, its
            // settings and its conversations are all exactly where they were.
            LifecycleRung::UsageExhausted->value => [
                'subject' => 'Paused — and one click un-pauses it',
                'text' => 'GOAIEZ: your credits are used up and service is paused. Your settings '
                    .'are unchanged. See your account: {link} Reply STOP to opt out.',
                'email_insert' => null,
            ],

            // PIII-29 D7. Email only — R32 authored no SMS for this beat and
            // this file does not write one.
            LifecycleRung::TrialDaySeven->value => [
                'subject' => "Halfway. Everything's still unlocked.",
                'text' => null,
                // PIII-29 D7 closes with the promise in writing; the guarantee canon makes that
                // sentence one sentence, in one place. The slot is what carries
                // it here.
                'email_insert' => '{guarantee}',
            ],

            LifecycleRung::TrialDayTen->value => [
                'subject' => "4 days left — here's what GOAIEZ did for you so far",
                'text' => '{name}, your GOAIEZ trial has 4 days left — and it\'s been working. Keep '
                    .'every review and message running: add a card now so nothing stops: {link}',
                'email_insert' => null,
            ],

            LifecycleRung::TrialDayThirteen->value => [
                'subject' => 'Tomorrow it pauses — unless you want it not to',
                'text' => "Last day tomorrow! Add your card now — just so you don't forget — and "
                    .'everything keeps running: {link}',
                'email_insert' => null,
            ],

            // ⛔ R39's RIDER, WITH THE PROMISE BOUND RATHER THAN PASTED. The
            // rider writes *"Your GOAIEZ trial ends [DATA]. Our promise in
            // writing: miss a 60-sec text-back and we fix it free + extend your
            // trial. Keep it on: [LINK]"* — the middle sentence is R39's
            // guarantee in R39's own words, and it is exactly the sentence
            // `legal.guarantee_sentence` exists to hold once. R32's own
            // non-guarantee landing line — *"Your GOAIEZ trial ended and service
            // is paused. Everything you set up is saved. Restart in one
            // minute"* — is superseded by the rider on this rung, which is what
            // the rider says: *"the trial-expiry rung … gains the guarantee line
            // immediately before the CTA."*
            LifecycleRung::TrialEnded->value => [
                'subject' => "Paused, not gone — everything's saved",
                'text' => 'Your GOAIEZ trial ends {date}. {guarantee} Keep it on: {link}',
                'email_insert' => '{guarantee}',
            ],
        ];
    }
}
