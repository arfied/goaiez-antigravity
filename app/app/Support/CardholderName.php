<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Requests\Billing\AuthorizeNetCheckoutRequest;
use App\Livewire\Account\Plan;
use App\Services\Billing\AuthorizeNetApi;
use RuntimeException;

/**
 * The name on the card — the `billTo` this gateway will not create a
 * subscription without.
 *
 * ⛔ **NO TENANT HAS EVER BEEN ABLE TO SUBSCRIBE, AND THIS IS THE WHOLE OF WHY.**
 * Both profile creators on {@see AuthorizeNetApi} sent no
 * `billTo` at all, so every `ARBCreateSubscriptionRequest` against the resulting
 * payment profile came back `E00014` — *"Bill-To First Name is required."* and
 * *"Bill-To Last Name is required."* — inside a 200. Three live probes against
 * the vendor's sandbox settled the shape and none of it is inferable from the
 * schema alone:
 *
 *   no `billTo` anywhere            `E00014`, twice, on the subscription create.
 *   `billTo` on the subscription    `E00093` — *"PaymentProfile cannot be sent
 *                                   with billing data."* It may not go there.
 *   `billTo` of first + last only,  ✅ `Ok`. The profile is created and the card
 *   on the payment profile          is validated.
 *
 * ⛔ **SO THE NAME IS REQUIRED, IT CANNOT GO ON THE SUBSCRIPTION, IT MUST GO ON
 * THE PAYMENT PROFILE, AND FIRST + LAST ALONE IS SUFFICIENT FOR THIS MERCHANT.**
 *
 * ## ⚠️ It is the CARDHOLDER, who need not be the account owner
 *
 * The owner's ruling: it is **collected at checkout**, not derived and not split
 * out of `users.name`. A business's card is routinely a partner's, a book-
 * keeper's or a parent company's, and a `billTo` carrying the wrong person's
 * name is an AVS mismatch on somebody else's statement.
 *
 * ⛔ **AND THERE WAS NOTHING IN THE SCHEMA TO DERIVE IT FROM ANYWAY.**
 * `businesses.address` is a `jsonb` column with `line1`/`city`/`region`/
 * `postal_code` keys and **no writer and no reader anywhere in `app/`** — the
 * only thing that fills it is `BusinessFactory`, so a lane reading the factory
 * and concluding *"we already hold a structured address"* is reading a test
 * fixture. `locations.address` is a free-text string naming a *place of
 * business* and is not a cardholder's billing address.
 *
 * ## ⚠️ A VALUE RATHER THAN TWO STRING PARAMETERS, FOR THE REASON
 * {@see PlanSelection} IS ONE
 *
 * {@see AuthorizeNetApi::createPaymentProfile()} already
 * took `(int, string, string)`; two more strings would make four adjacent
 * `string` parameters on a method whose other one is an **Accept.js nonce**, and
 * a transposition there sends the card token to the vendor as a person's first
 * name. A type cannot be transposed with a `string`, and the two names travel
 * together everywhere they travel at all.
 *
 * ⛔ **AND THE CONSTRUCTOR IS THE REFUSAL, SO AN EMPTY `billTo` IS NOT
 * REPRESENTABLE.** The signup path validates in
 * {@see AuthorizeNetCheckoutRequest} and the dunning
 * path guards inline in {@see Plan::replaceCard()}; both
 * exist to produce a sentence somebody can act on. This exists so that a third
 * surface written later cannot skip both and rebuild the defect.
 *
 * ⚠️ **IT THROWS `RuntimeException` RATHER THAN `InvalidArgumentException`, AND
 * THAT IS DELIBERATE ON A PAYMENT SCREEN.** Both surfaces already carry a
 * `catch (RuntimeException)` arm that answers with a sentence; an
 * `InvalidArgumentException` is a `LogicException`, would be caught by neither,
 * and a refusal that arrives as a 500 on the page somebody is trying to pay on
 * is worse than the refusal it is reporting.
 */
final readonly class CardholderName
{
    /**
     * ⚠️ **THE VENDOR'S OWN BOUND, NAMED ONCE.** `nameAndAddressType`'s
     * `firstName` and `lastName` each carry `<xs:maxLength value="50"/>` —
     * read from `AnetApiSchema.xsd` on **2026-08-29**, from both the sandbox
     * and the production host. Over it the vendor answers `E00015`, *"The field
     * length is invalid"*, which reaches a buyer as a refusal naming nothing.
     * The two surfaces read this constant rather than typing 50, so the day the
     * vendor moves it there is one edit.
     */
    public const int MAX_LENGTH = 50;

    private function __construct(
        public string $first,
        public string $last,
    ) {}

    /**
     * Build one from whatever a form or a Livewire argument supplied.
     *
     * @throws RuntimeException Nothing usable was given.
     */
    public static function fromInput(?string $first, ?string $last): self
    {
        return new self(self::part($first, 'first'), self::part($last, 'last'));
    }

    /**
     * The `billTo` object, in the vendor's own element order.
     *
     * ⛔ **`firstName` BEFORE `lastName` IS THE XSD's `xs:sequence` AND NOT A
     * STYLE CHOICE**, exactly as `billTo` before `payment` is on the profile
     * that carries it. The vendor's JSON is a projection of the schema and its
     * parser follows the sequence; its own reference says *"Developers using the
     * Authorize.net API should force the ordering of elements to match this API
     * Reference"*. Alphabetising them is an `E00003` on a request that is
     * otherwise perfect — and an `E00003` reaches the buyer as a declined card.
     *
     * ⚠️ **EVERY OTHER FIELD OF `customerAddressType` IS DELIBERATELY ABSENT** —
     * company, address, city, state, zip, country, phoneNumber, faxNumber,
     * email. The probe proved first + last alone is accepted by this merchant,
     * and this application does not hold a cardholder's address at all (see the
     * class docblock). Sending an *empty* one would be worse than sending none:
     * a blank `zip` is an AVS mismatch rather than an AVS non-answer.
     *
     * @return array{firstName: string, lastName: string}
     */
    public function billTo(): array
    {
        return [
            'firstName' => $this->first,
            'lastName' => $this->last,
        ];
    }

    /**
     * One half of the name, or the reason it is not usable.
     *
     * ⛔ **NO MESSAGE HERE EVER QUOTES THE VALUE.** One of the two things this
     * refuses is a value shaped like a card number, so the exception message —
     * which reaches a log and an error tracker — may name the field and nothing
     * else. It is the rule `AuthorizeNetRequestFailed` states for the vendor's
     * own `text` field, applied to our own refusal.
     *
     * ⛔ **THE CARD-SHAPED REFUSAL IS THE AUTOFILL TRIPWIRE AND IT IS THE ONE
     * TO READ TWICE.** These inputs carry `autocomplete="cc-given-name"` and
     * `cc-family-name`, which a browser fills from the **same stored card
     * record** that fills `cc-number` — and unlike every other input on either
     * card form, they have a `name` and are serialised. So the day a browser,
     * an extension or a password manager mis-maps that group, a PAN arrives in
     * a field this application posts to its own server. It is refused here
     * rather than forwarded to the vendor as somebody's first name.
     *
     * @throws RuntimeException
     */
    private static function part(?string $value, string $half): string
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            throw new RuntimeException("A cardholder's {$half} name is required by Authorize.Net and none was given.");
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new RuntimeException(
                "A cardholder's {$half} name is at most ".self::MAX_LENGTH.' characters at Authorize.Net.'
            );
        }

        if (CardNumberShape::looksLikeOne($trimmed)) {
            throw new RuntimeException("A card-shaped value reached the cardholder's {$half} name field.");
        }

        return $trimmed;
    }
}
