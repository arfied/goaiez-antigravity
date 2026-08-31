<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;

/**
 * The `Retry-After` response header, turned into a number of seconds.
 *
 * ## The specification, read on 2026-08-20 and not from memory
 *
 * ⛔ **THE FIELD HAS TWO FORMS AND A CLIENT THAT ONLY HANDLES THE NUMBER IS
 * WRONG ABOUT THE OTHER ONE SILENTLY.** RFC 9110 §10.2.3, verbatim:
 *
 * ```
 * Retry-After = HTTP-date / delay-seconds
 *
 * A delay-seconds value is a non-negative decimal integer, representing
 * time in seconds.
 *
 * delay-seconds  = 1*DIGIT
 *
 * Two examples of its use are
 *
 * Retry-After: Fri, 31 Dec 1999 23:59:59 GMT
 * Retry-After: 120
 * ```
 *
 * (`www.rfc-editor.org/rfc/rfc9110.txt`, RFC 9110, June 2022, fetched
 * 2026-08-20.)
 *
 * ⚠️ **IT IS `delay-seconds` AND NOT `delta-seconds`.** RFC 7231 called it
 * `delta-seconds`; RFC 9110 obsoleted 7231 and renamed the rule. Nothing about
 * the wire format changed; the name did, and a comment citing the old one sends
 * the next reader to the wrong section of the wrong document.
 *
 * ⛔ **AND `HTTP-date` IS THREE FORMATS, NOT ONE, AND ACCEPTING ALL THREE IS A
 * `MUST`.** RFC 9110 §5.6.7: *"A recipient that parses a timestamp value in an
 * HTTP field MUST accept all three HTTP-date formats"* — `IMF-fixdate`, the
 * obsolete RFC 850 format and ANSI C's `asctime()`. The same section: *"values
 * in the asctime format are assumed to be in UTC"*, which is why the zone is
 * supplied here rather than inferred, and *"Recipients of timestamp values are
 * encouraged to be robust in parsing timestamps"*, which is why the asctime
 * form's documented double space (`date3 = month SP ( 2DIGIT / ( SP 1DIGIT ))`)
 * is tolerated rather than normalised away.
 *
 * ⚠️ **RFC 9110 DOES NOT LIST `429` AMONG THE STATUSES THAT CARRY THIS FIELD**
 * — it names 503 and any 3xx. `429` and its use of the field are RFC 6585 §4
 * (April 2012): *"The response representations SHOULD include details
 * explaining the condition, and MAY include a Retry-After header indicating how
 * long to wait before making a new request"*
 * (`www.rfc-editor.org/rfc/rfc6585.html`, fetched 2026-08-20). **MAY**, so a
 * `429` carrying no header at all is a conforming `429`, and a client that only
 * backs off when it is given a number does not back off on the commonest real
 * response. {@see OutboundSiteBudget::noteResponse()} is where that is decided;
 * this file only reads the field.
 *
 * ⛔ **THE 50-YEAR RULE IS DELIBERATELY NOT IMPLEMENTED, AND THE REASON IS
 * 256.** §5.6.7 requires that a two-digit `rfc850-date` year *"more than 50
 * years in the future"* be read as the most recent past year with the same last
 * two digits. PHP's own two-digit pivot is fixed at 69/70, so the furthest
 * future year this parser can ever produce is **2069** — 43 years from the date
 * this was written, and shrinking by one every year. The branch could not run,
 * and a test for it would pass vacuously. Written down rather than coded.
 */
final class RetryAfter
{
    /**
     * The three forms of `HTTP-date`, in the order §5.6.7 lists them.
     *
     * ⚠️ **`!` RESETS EVERY FIELD THE FORMAT DOES NOT NAME.** Without it, a form
     * that omits a field inherits the current time's value for it, so the same
     * header parses to a different instant every second.
     *
     * @var list<string>
     */
    private const array DATE_FORMATS = [
        '!D, d M Y H:i:s \G\M\T',   // IMF-fixdate:  Sun, 06 Nov 1994 08:49:37 GMT
        '!l, d-M-y H:i:s \G\M\T',   // rfc850-date:  Sunday, 06-Nov-94 08:49:37 GMT
        '!D M j H:i:s Y',           // asctime-date: Sun Nov  6 08:49:37 1994
    ];

    /**
     * The most digits a `delay-seconds` value may have before it is clamped.
     *
     * ⚠️ **A GUARD RATHER THAN A CAST, BECAUSE THE CAST'S ANSWER IS A GUESS.**
     * `(int)` on a numeric string wider than a PHP integer saturates at
     * `PHP_INT_MAX` on this build, and that is a property of an implementation
     * rather than of the language. Eighteen digits is the widest value that
     * cannot overflow a 64-bit integer, so anything longer is answered without
     * relying on the cast at all.
     */
    private const int MAX_DIGITS = 18;

    /**
     * How long the sender asked us to wait, or `null` if it did not say.
     *
     * ⛔ **`null` AND `0` ARE DIFFERENT ANSWERS AND THE CALLER MUST NOT COLLAPSE
     * THEM.** `null` is *"there was no readable instruction"*; `0` is *"the
     * instant named has already passed"*, which is an instruction. A parser that
     * answered `0` for a malformed header would turn a header nobody understood
     * into permission to retry immediately — the exact failure this class exists
     * to make impossible.
     *
     * @param  string|null  $header  The raw field value. Laravel's HTTP client
     *                               answers `''` for a header a response does
     *                               not carry, which is handled here as absent.
     */
    public static function seconds(?string $header, ?CarbonImmutable $now = null): ?int
    {
        if ($header === null) {
            return null;
        }

        $value = trim($header);

        if ($value === '') {
            return null;
        }

        $now ??= CarbonImmutable::now();

        if (preg_match('/^[0-9]+$/', $value) === 1) {
            $digits = ltrim($value, '0');

            return mb_strlen($digits) > self::MAX_DIGITS ? PHP_INT_MAX : (int) $value;
        }

        return self::fromDate($value, $now);
    }

    /**
     * One of the three `HTTP-date` forms, as a delay from now.
     *
     * ⚠️ **A DATE IN THE PAST IS `0` AND NOT `null`.** A server that names an
     * instant which has already gone by has said *"you may retry"*, and reading
     * that as *"unparseable"* would apply a default wait the sender did not ask
     * for.
     */
    private static function fromDate(string $value, CarbonImmutable $now): ?int
    {
        $utc = new DateTimeZone('UTC');

        foreach (self::DATE_FORMATS as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $value, $utc);

            if (! $parsed instanceof DateTimeImmutable) {
                continue;
            }

            $errors = DateTimeImmutable::getLastErrors();

            // ⚠️ **A PARSE THAT WARNED IS A PARSE THAT GUESSED.** A trailing
            // tail after a complete date, and a day the month does not have,
            // both produce an object *and* a warning; taking the object would
            // honour a wait the sender never expressed.
            if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                continue;
            }

            return max(0, $parsed->getTimestamp() - $now->getTimestamp());
        }

        return null;
    }
}
