<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Enums\BillingTerm;
use App\Services\Billing\AuthorizeNetApi;
use App\Services\Billing\AutoRenewalAcknowledgements;
use App\Support\CardholderName;
use App\Support\CardNumberShape;
use App\Support\PlanSelection;
use Illuminate\Contracts\Validation\Rule as RuleContract;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The Accept.js nonce, and the refusal that keeps SAQ-A true (decision 2145).
 *
 * ⛔ **NO CARD NUMBER MAY EVER TOUCH THIS APPLICATION** — decision 2056, and it
 * survives every gateway change. Accept.js posts the card straight from the
 * browser to Authorize.Net and hands the page an opaque nonce, so the form this
 * request validates should carry a token and nothing else.
 *
 * ⚠️ **"SHOULD" IS NOT "DOES", WHICH IS WHY THIS REQUEST ACTIVELY LOOKS.** The
 * failure that ends SAQ-A is not a decision anybody makes; it is one line of
 * JavaScript. A `name` attribute left on a card input, an autofill, a browser
 * extension, a developer testing the form with JS disabled — any of them posts a
 * PAN to this endpoint, and every one of them is invisible in a diff of the PHP.
 * The consequence is not a bug: it is a card number in a request log, an
 * exception tracker and a PCI scope this business is not assessed for.
 *
 * So the rule below is `prohibited`-shaped rather than merely absent: an
 * unexpected field is refused rather than ignored, and a value that **looks like
 * a card number** fails validation and is reported as a defect — with the value
 * itself never touching the log line.
 */
final class AuthorizeNetCheckoutRequest extends FormRequest
{
    /**
     * ⚠️ **THE FIELDS THIS FORM MAY CARRY, AS AN ALLOWLIST.** A denylist of
     * "card fields" is what a lint tuned until it catches nothing looks like
     * (511): `cardNumber`, `cc-number`, `number`, `pan`, `cardnum` and a dozen
     * spellings, and the one somebody's JavaScript actually uses is the
     * thirteenth. An allowlist has no thirteenth.
     *
     * ⚠️ **IT GREW BY TWO ON 2026-08-12 AND THAT IS A DELIBERATE ACT** (decision
     * 2680). `term` and `instalments` are what the card form now carries besides
     * the nonce, and widening an allowlist whose whole job is to be narrow is
     * exactly the change that should be visible in a diff. Neither is a card
     * field, neither is free text — one is checked against a backed enum and the
     * other is a boolean — and the refusal below is unchanged for everything
     * else.
     *
     * ⚠️ **AND BY ONE MORE ON 2026-08-12, EQUALLY DELIBERATELY** (2980–2999).
     * `auto_renewal_ack` is the box California's Automatic Renewal Law requires
     * to be ticked **separately from the purchase itself**, and what it produces
     * is a proof record rather than a boolean
     * ({@see AutoRenewalAcknowledgements}). It is not a
     * card field and it is not free text — `accepted` takes exactly the values a
     * ticked checkbox posts — and the refusal below is unchanged for everything
     * else.
     *
     * ⚠️ **AND BY ONE MORE ON 2026-08-15, WITH THE SAME DELIBERATION** (2753's
     * finding, closed). `locations` is how many places of business the plan is
     * being bought for, and until this slice every checkout passed a hard zero
     * because there was nowhere to say. It is not a card field and it is not free
     * text — an integer bounded by
     * {@see BillingTermRequest::MAX_ADDITIONAL_LOCATIONS} — and the refusal below
     * is unchanged for everything else.
     *
     * ⚠️ **AND BY ONE MORE ON 2026-08-17, WHICH IS THE ONE TO READ TWICE** (4640).
     * `quoted_total_cents` is what the page displayed, and it is the only field
     * here that looks like money. ⛔ **IT IS NEVER CHARGED, STORED OR SHOWN** —
     * it is compared against the figure this server derives at submit time and
     * throws the purchase out if the two differ. A price accepted *from* a form
     * is what `PlanSelection` refuses to hold at all; a price compared *against*
     * one cannot be raised, lowered or forged into anything but a refusal, which
     * is why it needs no signature and why widening the allowlist for it is safe.
     *
     * ⛔ **AND BY TWO MORE ON 2026-08-29, WHICH ARE THE FIRST FIELDS EVER ADDED
     * HERE THAT CARRY A NATURAL PERSON'S NAME.** `cardholder_first_name` and
     * `cardholder_last_name` are the vendor's `billTo.firstName` and
     * `billTo.lastName`, and **without them no tenant can subscribe at all**:
     * every `ARBCreateSubscriptionRequest` against a `billTo`-less payment
     * profile comes back `E00014`, *"Bill-To First Name is required."* They are
     * collected rather than derived by the owner's ruling — ⚠️ **the cardholder
     * is not necessarily the account owner**, so `users.name` is the wrong
     * answer and splitting it in two is a worse one.
     *
     * ⛔ **THEY ARE ALSO THE ONLY FIELDS ON THIS FORM A BROWSER FILLS FROM THE
     * STORED **CARD** RECORD.** Every card input on the page carries an `id` and
     * no `name` — *that is the whole of SAQ-A on this page* — and these two
     * carry `autocomplete="cc-given-name"` / `cc-family-name`, which is the same
     * autofill group as `cc-number`, **and a `name`**. So the tripwire below no
     * longer reads `data_value` alone: it reads every value this form submits,
     * and {@see CardholderName} refuses a card-shaped name a second time at the
     * boundary the Livewire surface reaches instead of this one.
     *
     * @var list<string>
     */
    private const array PERMITTED = [
        'data_descriptor',
        'data_value',
        'cardholder_first_name',
        'cardholder_last_name',
        'term',
        'instalments',
        'locations',
        'quoted_total_cents',
        'auto_renewal_ack',
        '_token',
    ];

    /**
     * The route already sits behind `auth` and the tenant is resolved from the
     * session, so the person is subscribing their own business or nobody's.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * ⚠️ The union carries `RuleContract` because `Rule::enum()` still returns a
     * **legacy** `Illuminate\Contracts\Validation\Rule` rather than a
     * `ValidationRule` — checked in the installed framework, and annotated
     * honestly rather than widened to quiet the analyser.
     *
     * @return array<string, list<RuleContract|ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            // ⚠️ PINNED TO THE VENDOR'S ONE VALUE RATHER THAN ACCEPTED AS FREE
            // TEXT. There is exactly one descriptor for a payment nonce
            // (`COMMON.ACCEPT.INAPP.PAYMENT`, verified 2026-08-11), so anything
            // else is either a different product or a forged form.
            'data_descriptor' => ['required', 'string', 'in:'.AuthorizeNetApi::OPAQUE_DATA_DESCRIPTOR],

            // The nonce. Length-capped before anything pattern-matches it, so a
            // megabyte of text is rejected without being scanned.
            'data_value' => ['required', 'string', 'max:512'],

            // ⛔ `required`, BECAUSE THE VENDOR REQUIRES IT AND A CHECKOUT
            // WITHOUT IT CANNOT COMPLETE. `nullable` here would let a stale tab
            // post the shape that has never once worked, and the person would
            // be told their card was declined.
            //
            // ⚠️ **THE BOUND IS THE VENDOR'S AND IS NAMED FROM THE ONE PLACE.**
            // `nameAndAddressType`'s `firstName` and `lastName` are
            // `maxLength="50"` in `AnetApiSchema.xsd`; a second literal here is
            // a second ceiling, and the day they disagree is the day this form
            // accepts a name the vendor answers `E00015` to.
            'cardholder_first_name' => ['required', 'string', 'max:'.CardholderName::MAX_LENGTH],
            'cardholder_last_name' => ['required', 'string', 'max:'.CardholderName::MAX_LENGTH],

            // ⚠️ THE TERM IS CHECKED AGAINST THE ENUM AND NOT AGAINST A LIST OF
            // STRINGS HERE. A `in:monthly,annual` would be a second place the
            // terms are enumerated, and the day a third one exists it is the
            // place nobody edits.
            'term' => ['nullable', 'string', Rule::enum(BillingTerm::class)],
            'instalments' => ['nullable', 'boolean'],

            // ⚠️ THE SAME BOUND AS THE GET SCREEN THAT QUOTED IT, NAMED FROM THE
            // ONE PLACE. A second literal here is a second ceiling, and the day
            // they disagree is the day this form charges for a quantity the page
            // before it refused to price.
            'locations' => ['nullable', 'integer', 'min:0', 'max:'.BillingTermRequest::MAX_ADDITIONAL_LOCATIONS],

            // ⛔ `required`, NOT `nullable`, AND THAT IS THE WHOLE OF THE GUARD
            // (4640). A check anybody can switch off by omitting a field is a
            // check nobody has. An old tab or a page cached before this shipped
            // therefore fails validation and is refused — which is a refresh for
            // that person, against a charge at a price they never saw for
            // everybody else. `min:0` rather than `min:1`: what it must not
            // accept is a negative, and comparing zero to a real total refuses
            // exactly as it should.
            'quoted_total_cents' => ['required', 'integer', 'min:0'],

            // ⚠️ `accepted`, NOT `boolean`, AND THE DIFFERENCE IS THE WHOLE
            // REQUIREMENT (2980–2999). An unticked checkbox is not submitted at
            // all, so `boolean` would let a missing field through as false and
            // a subscription would be created with no acknowledgment recorded —
            // which is the state California's Automatic Renewal Law is about.
            // `CancelSubscriptionRequest` uses the same rule for the same
            // reason, one screen away.
            'auto_renewal_ack' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'auto_renewal_ack.accepted' => 'Tick the box to confirm you understand when you are charged.',

            // ⚠️ `22`'s outcome language, and the *cardholder* rather than "your
            // name": the person filling this in is not always the person whose
            // card it is, and the vendor checks it against the card.
            'cardholder_first_name.required' => 'Enter the first name exactly as it appears on the card.',
            'cardholder_last_name.required' => 'Enter the last name exactly as it appears on the card.',
        ];
    }

    /**
     * The validated input as the value the gateway takes.
     *
     * ⚠️ **BUILT FROM `validated()`, NEVER FROM `input()`.** The whole file
     * exists because this endpoint must act only on fields it has checked, and a
     * selection assembled from raw input would walk straight past that.
     */
    public function selection(): PlanSelection
    {
        $term = $this->validated('term');
        $locations = $this->validated('locations');

        return PlanSelection::fromInput(
            is_string($term) ? $term : null,
            (bool) $this->validated('instalments'),
            // ⚠️ `(int)` OVER A VALIDATED FIELD, NOT `$this->integer()`. That
            // helper reads raw input and would walk straight past the allowlist
            // this whole file exists to enforce — the same trap the method's
            // docblock names for `term`.
            is_numeric($locations) ? (int) $locations : 0,
        );
    }

    /**
     * What the page this was submitted from displayed as the total, in minor
     * units (4640).
     *
     * ⛔ **THE CALLER COMPARES IT AND MUST NEVER SPEND IT.** It exists so that a
     * founder window opening or closing between the `GET` and the `POST` refuses
     * the purchase instead of charging a figure nobody was shown. Read from
     * `validated()` for `selection()`'s reason: `$this->integer()` walks straight
     * past the allowlist this whole file exists to enforce.
     */
    public function quotedTotalMinorUnits(): int
    {
        return (int) $this->validated('quoted_total_cents');
    }

    /**
     * The name on the card, as the vendor's `billTo` takes it.
     *
     * ⚠️ **BUILT FROM `validated()` FOR {@see self::selection()}'s REASON** — a
     * value assembled from raw input would walk straight past the allowlist this
     * whole file exists to enforce.
     *
     * ⚠️ **IT CANNOT THROW FROM HERE, AND THAT IS A PROPERTY OF THE RULES ABOVE
     * RATHER THAN OF THIS METHOD.** {@see CardholderName::fromInput()} refuses
     * an empty name, a name over the vendor's 50 characters and a card-shaped
     * one; `required`, `max:` and the tripwire in
     * {@see self::withValidator()} have each already refused all three with a
     * sentence somebody can act on. The constructor stays strict anyway, because
     * it is what a *third* surface written later would meet.
     */
    public function cardholderName(): CardholderName
    {
        $first = $this->validated('cardholder_first_name');
        $last = $this->validated('cardholder_last_name');

        return CardholderName::fromInput(
            is_string($first) ? $first : null,
            is_string($last) ? $last : null,
        );
    }

    /**
     * ⚠️ The two refusals that are about PCI scope rather than about validity.
     *
     * ⚠️ **THE SECOND ONE READ ONE FIELD UNTIL 2026-08-29 AND NOW READS THEM
     * ALL.** See the loop's own comment: the page grew its first inputs that a
     * browser autofills from the stored **card** record, and they are the first
     * inputs on it that are serialised at all.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $unexpected = array_diff(array_keys($this->all()), self::PERMITTED);

            if ($unexpected !== []) {
                // ⚠️ THE KEYS, NEVER THE VALUES. The whole point of this branch
                // is that one of those values may be a card number, so naming
                // the field is the most that may be said about it — and it is
                // enough, because the fix is in the form that sent it.
                Log::warning('unexpected fields posted to the card form', [
                    'fields' => array_values($unexpected),
                ]);

                $validator->errors()->add(
                    'data_value',
                    'That form submitted more than it should have. Please refresh and try again.',
                );

                return;
            }

            // ⛔ **EVERY FIELD, NOT ONLY THE NONCE — AND THAT WIDENING IS THE
            // PRICE OF THE TWO NAME FIELDS.** Until 2026-08-29 the only named
            // inputs on this page were a nonce, a term, a count and a hidden
            // total; the two cardholder fields are the first that a browser
            // fills from the **stored card record**, in the same autofill group
            // as `cc-number`, and they are serialised where every card input on
            // the page deliberately is not. A tripwire that reads one field is a
            // tripwire across the door somebody is no longer coming through.
            //
            // ⚠️ The loop is safe over the whole payload because the branch
            // above has already returned for anything outside the allowlist.
            foreach ($this->all() as $field => $value) {
                if (! is_string($value) || ! CardNumberShape::looksLikeOne($value)) {
                    continue;
                }

                // ⚠️ NOTHING ABOUT THE VALUE IS LOGGED, NOT EVEN ITS LENGTH.
                // A length plus a timestamp plus a tenant is more than enough to
                // be a problem in an incident report, and none of it helps fix
                // the JavaScript that caused this. ⚠️ **The FIELD is named**,
                // because that is what says whether Accept.js stopped tokenising
                // or an autofill put a card number in a name box, and the fix is
                // a different line of JavaScript in each case.
                Log::error('a card-shaped value reached the card form', ['field' => $field]);

                $validator->errors()->add(
                    is_string($field) ? $field : 'data_value',
                    'We could not process that securely. Please refresh and try again.',
                );

                return;
            }
        });
    }
}
