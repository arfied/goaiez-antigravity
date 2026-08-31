<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Location;
use App\Services\AuditService;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only writer of `locations.primary_phone` and `locations.address`.
 *
 * ⛔ **BOTH COLUMNS SHIPPED WITH STAGE 0 AND HAD NO WRITER IN `app/` UNTIL THIS
 * CLASS** — only `LocationFactory` set them, which is `businesses.pixel_tenant_id`
 * (4961) and `locations.website_url` (5540) for the third time. **This one is
 * the worst of the three, because two shipped readers were already depending on
 * it and both degraded silently with a green suite**: the carrier-mandated HELP
 * reply and the assistant's directions skill. See the creating migration.
 *
 * ## What is stored is what the owner typed, and nothing is normalised into it
 *
 * ⛔ **THE PHONE NUMBER IS KEPT VERBATIM — NOT E.164, NOT REFORMATTED.** It is
 * printed, unchanged, into a text message sent to a member of the public who
 * asked *"who is texting me"*. `LocationWebsite::normalise()`'s rule is that
 * *what the owner confirms is the value we will act on*, and here the value we
 * act on **is** the string: rewriting `(901) 555-0182` into `+19015550182` after
 * a confirmation would send a stranger a number the owner never agreed to print.
 * Whitespace is collapsed and the ends are trimmed; nothing else is touched.
 *
 * ⚠️ **SO THIS CLASS DEFINES NO COMPARISON AND MUST NOT GROW ONE.** *"Is this
 * the same phone number"* already has an implementation —
 * `App\Services\Audit\PageText::phoneDigits()` and `containsPhone()`, with 231's
 * bug written up in its docblock — and the consistency engine of rows 13 and 14
 * is what will call it. A second answer here is how the two come to disagree.
 *
 * ## ⛔ THE VALIDATOR IS A COMPLIANCE CONTROL, NOT A TIDINESS ONE
 *
 * `ComplianceReplies`' own docblock says the HELP reply carries **no link,
 * deliberately** — *"a URL in an auto-reply to an unknown number is what carrier
 * link-filtering exists to catch"* (2105). This column is interpolated straight
 * into that body. **Before this class existed nothing could put anything there,
 * because nothing wrote the column at all**; giving it a writer is what makes
 * `Call us at example.com/contact` a reachable state. {@see self::normalisePhone()}
 * therefore refuses every character that is not a digit, a space or one of
 * `+ - ( ) .`, which forecloses a URL, an email address and a second sentence at
 * once. **It is not "be lenient and let the owner decide"** — the owner is not
 * the person the message is sent to.
 *
 * ⚠️ **AND THE LENGTH CEILING IS THE SAME RULE ONE STEP FURTHER OUT.**
 * `ComplianceReplies::CARRIER_FIELD_LIMIT` is 320 characters, and that body
 * answers an overlong *business name* by dropping the attribution (3284) — the
 * only variable part it had. The contact clause sits inside the fallback the
 * attribution is dropped **to**, so a 255-character phone number composes a
 * reply that is over the limit with nothing left to drop. {@see self::PHONE_MAX}
 * is what stops that being representable, and a test drives the arithmetic
 * rather than trusting this paragraph.
 *
 * ## Nothing here is derived, and that is the design constraint rather than a gap
 *
 * ⛔ **THE VALUE IS THE OWNER'S, STATED — NEVER GOOGLE'S, ADOPTED.**
 * `PlaceSummary` already carries `formattedAddress` and `nationalPhoneNumber`,
 * and `PlaceConfirmation` shows the address on the confirm card and discards it.
 * Adopting it would be cheap and it is refused: rows 13 and 14 are a consistency
 * engine that compares this record against every directory, and **if this record
 * is Google's copy of the business the engine spends its life checking Google
 * against itself while telling the owner their own website is wrong.**
 *
 * ⚠️ **AND THE CONFIRM CARD IS NOT A CONFIRMATION OF THE ADDRESS.** What the
 * owner presses there answers *"is this your business?"*; `PlaceConfirmation`'s
 * own docblock is explicit that it *"CONFIRMS OWNERSHIP OF NOTHING"*. Treating
 * that press as an owner-confirmed address is silent adoption wearing a
 * confirmation's clothes. A pre-fill the owner edits is a different and probably
 * better thing, and it needs a ruling and a source column first — decision 6106.
 *
 * ## One method, both values, one transaction
 *
 * ⚠️ **NOT `statePhone()` AND `stateAddress()`.** The screen shows both boxes at
 * once with what we hold already in them, so a save is a statement about the
 * pair: *this is how customers reach us*. Two methods would let a caller write
 * half of that statement and audit it as the whole of it, and a business that
 * has cleared its address is telling us something as definite as one that has
 * set it.
 */
final class LocationDetails
{
    /**
     * The longest phone number this platform will print.
     *
     * ⚠️ **DERIVED FROM THE CARRIER FIELD LIMIT, NOT PICKED.** The longest
     * fallback HELP body is `GO AI EZ. Contact: {phone}. Msg&data rates may
     * apply. Reply STOP to opt out.` — 71 fixed characters — so 32 leaves the
     * 320-character ceiling untouched by a wide margin while comfortably fitting
     * an international number written the way somebody writes one.
     * `LocationDetailsTest` computes the real body and asserts the margin, so
     * this constant cannot drift away from the thing it is protecting.
     */
    public const int PHONE_MAX = 32;

    /**
     * The longest address this platform will hold.
     *
     * ⚠️ **BOUNDED BECAUSE IT ENTERS AN AI PROMPT**, not because a database
     * needs it. `AgentComposer::facts()` puts it in front of a model on every
     * turn of every thread where skill 2 is lit, and an unbounded field there is
     * a per-turn cost with no ceiling. 200 characters holds any postal address
     * written on one line.
     */
    public const int ADDRESS_MAX = 200;

    /**
     * The one refusal sentence for every malformed phone, so an owner is not
     * told three different things about one mistake.
     */
    private const string NOT_A_PHONE = 'That does not look like a phone number. Type the number your '
        .'customers ring, using digits — for example (901) 555-0182.';

    public function __construct(private readonly AuditService $audit) {}

    /**
     * Record how customers reach this business.
     *
     * ⚠️ **NULL IS A STATEMENT AND NOT AN OMISSION.** An owner who clears the
     * address box is telling us they have no public address — a service-area
     * business has none, and `28` has no place for one either — so the column and
     * its confirmation both go, which is the only state the CHECK permits and the
     * only one the two readers can honestly act on.
     *
     * @param  ?string  $phone  What the owner typed, before whitespace is
     *                          collapsed. Null or blank clears it.
     * @param  ?string  $address  Likewise.
     * @param  string  $actor  An actor label — `user:14`, never a bare id.
     * @param  true  $confirmed  The owner said "yes, that is how customers reach
     *                           us". Typed as PHP's literal `true` on 220's rule:
     *                           a caller holding a plain `bool` cannot call this
     *                           at all without narrowing it first, and deleting
     *                           the narrowing does not compile.
     *
     * @throws InvalidArgumentException when a value is not one this platform
     *                                  could print to a stranger
     */
    public function state(
        Location $location,
        ?string $phone,
        ?string $address,
        string $actor,
        true $confirmed,
    ): Location {
        $this->assertBelongsToTenant($location);

        $phone = self::normalisePhone($phone);
        $address = self::normaliseAddress($address);

        $before = [
            'primary_phone' => $location->primary_phone,
            'address' => $location->address,
        ];

        DB::transaction(function () use ($location, $phone, $address, $before, $actor): void {
            // ⚠️ **ONE TIMESTAMP FOR BOTH, NOT TWO CALLS TO `now()`.** A save is
            // one statement about the pair, and two microsecond-apart values
            // would let a later reader conclude the two were confirmed on
            // separate occasions — which is the question this column exists to
            // answer and the one it would then answer wrongly.
            $at = now();

            $location->forceFill([
                'primary_phone' => $phone,
                // ⚠️ **THE CONFIRMATION MOVES EVERY TIME, INCLUDING WHEN THE
                // VALUE DOES NOT.** It is not "when this was first set" — it is
                // *when a person last said this is still true*, which is the
                // question a consistency engine has to ask before it tells
                // somebody a directory is wrong about them.
                'primary_phone_confirmed_at' => $phone === null ? null : $at,
                'address' => $address,
                'address_confirmed_at' => $address === null ? null : $at,
            ])->save();

            // ⚠️ **BOTH SIDES.** `AuditService`'s rule is that an entry recording
            // only the new value cannot answer what happened — and here the
            // question a wrong value raises is *what did it used to be*, because
            // the old number is what a customer was given last week.
            //
            // ⚠️ **NO SEPARATE RAW PASTE, UNLIKE `LocationWebsite`.** That class
            // keeps one because normalisation can turn an owner's answer into a
            // different address; nothing here drops anything but whitespace, so a
            // second copy would record the same string twice.
            $this->audit->recordChange(
                'location.contact_details_confirmed',
                $actor,
                $before,
                ['primary_phone' => $phone, 'address' => $address],
                $location,
            );
        });

        return $location;
    }

    /**
     * The phone number we will print, or null when there is none.
     *
     * ⚠️ **PURE AND PUBLIC SO A SCREEN CAN REFUSE BEFORE IT ASKS ANYBODY TO
     * CONFIRM**, and {@see self::state()} runs it again regardless, on 398's rule
     * that an inner guard must never depend on an outer one.
     *
     * ⛔ **LETTERS ARE REFUSED OUTRIGHT, INCLUDING `ext`.** An extension is a
     * real thing a business has, and permitting the three letters that spell it
     * means permitting letters, which is the whole of the URL hole above. The
     * trade is recorded rather than hidden: an owner with an extension types the
     * main number, and the person who texted HELP reaches a human either way.
     *
     * @throws InvalidArgumentException
     */
    public static function normalisePhone(?string $phone): ?string
    {
        $collapsed = self::collapse($phone);

        if ($collapsed === null) {
            return null;
        }

        if (mb_strlen($collapsed) > self::PHONE_MAX) {
            throw new InvalidArgumentException(
                'That is longer than a phone number. Type just the number your customers ring.'
            );
        }

        // Every character has to be one somebody writes a phone number with. A
        // `+` is permitted only where it means a country code — leading, once.
        if (preg_match('/^\+?[0-9 ().-]+$/', $collapsed) !== 1) {
            throw new InvalidArgumentException(self::NOT_A_PHONE);
        }

        $digits = preg_replace('/\D+/', '', $collapsed) ?? '';

        // Seven is the shortest a subscriber number gets; fifteen is E.164's own
        // ceiling, so anything longer is not a number anybody can dial.
        if (strlen($digits) < 7 || strlen($digits) > 15) {
            throw new InvalidArgumentException(self::NOT_A_PHONE);
        }

        return $collapsed;
    }

    /**
     * The address we will hold, or null when there is none.
     *
     * ⚠️ **DELIBERATELY THIN.** The temptation is to require a street number and
     * a postcode, because that is what a comparison needs — and decision 231's
     * own bug is what happens when a permissive pattern decides what an address
     * is. **That decision goes the other way here**: refusing *"Unit B, The Old
     * Mill, Bakewell"* because it has no number would be this platform telling a
     * business its own address is wrong, which is exactly what 231 forbids. What
     * an undecomposable address costs is that the consistency engine reports
     * *cannot compare* for it, which is a state that engine already has.
     *
     * @throws InvalidArgumentException
     */
    public static function normaliseAddress(?string $address): ?string
    {
        $collapsed = self::collapse($address);

        if ($collapsed === null) {
            return null;
        }

        if (mb_strlen($collapsed) > self::ADDRESS_MAX) {
            throw new InvalidArgumentException(
                'That is longer than we can hold. Give the address the way it appears on your door.'
            );
        }

        return $collapsed;
    }

    /**
     * One line, trimmed, with every run of whitespace reduced to one space.
     *
     * ⛔ **THE NEWLINE IS THE POINT RATHER THAN THE TIDINESS.** A stored value
     * carrying a line break is one that breaks out of its own line in
     * `AgentComposer::facts()`, where the block is assembled as lines and each
     * one reads to a model as a separate fact. The prompt fence stops that value
     * being read as an *instruction*; it does not stop it being read as a
     * different fact.
     */
    private static function collapse(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $collapsed = trim((string) preg_replace('/\s+/u', ' ', $value));

        return $collapsed === '' ? null : $collapsed;
    }

    /**
     * The wrong-tenant refusal — the case RLS cannot catch once a model is in
     * hand, refused here for {@see LocationWebsite}'s reason.
     */
    private function assertBelongsToTenant(Location $location): void
    {
        if ($location->business_id === Tenancy::idOrFail()) {
            return;
        }

        throw new InvalidArgumentException(
            'That location belongs to another tenant. This is the number a carrier-mandated reply '
            .'prints to a member of the public, so writing it here would answer a stranger with '
            .'somebody else\'s contact details.',
        );
    }
}
