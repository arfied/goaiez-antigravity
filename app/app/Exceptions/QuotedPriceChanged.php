<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\PlanQuote;
use App\Support\PlanSelection;
use RuntimeException;

/**
 * The price moved between the page that quoted it and the card that would be
 * charged (T176 P1 fix wave, decision 4640).
 *
 * ⛔ **A FOUNDER WINDOW CLOSING BETWEEN THE `GET` AND THE `POST` IS THE WHOLE
 * SUBJECT.** The card page renders the total, the instalment schedule and
 * California's renewal disclosure from the offer live when it was drawn; the
 * submit resolves the offer again. Nothing shares those two resolutions and
 * nothing can — they are separate requests, minutes apart — so 4346's fix
 * (carrying the numbers on a {@see PlanQuote}) closes a gap *inside*
 * one request and cannot reach across two.
 *
 * ⛔ **SO THE POSTED FIGURE IS A CHECK AND NEVER A PRICE.** The form carries what
 * the page displayed, and it is compared against the figure the server has just
 * derived; it is never charged, stored or shown. That is the difference between
 * this and a price posted by a form, which {@see PlanSelection}'s
 * docblock refuses outright — and it is why the field needs no signature.
 * Tampering with it can only produce a mismatch, and a mismatch only ever
 * refuses.
 *
 * ⚠️ **IT FAILS CLOSED AND MUST.** The alternative — charge today's price,
 * because that is what the plan costs now — bills somebody a figure they never
 * saw, against an Automatic Renewal Law acknowledgment recording a different
 * one. Both halves right on their own screen, which is 3444's shape with a
 * statute attached.
 *
 * ⚠️ **A NAMED TYPE, BECAUSE THE CONTROLLER ALREADY CATCHES `RuntimeException`
 * AND SENDS THAT PERSON AWAY WITH NO MESSAGE.** "You are already subscribed" and
 * "the price on your screen is out of date" want different words: the first is a
 * state nobody can act on, the second is fixed by reading the new figure and
 * pressing the button again.
 */
final class QuotedPriceChanged extends RuntimeException
{
    public static function between(int $quotedMinorUnits, int $chargingMinorUnits): self
    {
        return new self(
            "The page quoted {$quotedMinorUnits} minor units and this purchase would be charged "
            ."{$chargingMinorUnits}. A price window opened or closed between the two, and charging "
            .'the second figure against a confirmation of the first is not something this '
            .'application will do.'
        );
    }
}
