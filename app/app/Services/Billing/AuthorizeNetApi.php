<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Exceptions\AuthorizeNetRequestFailed;
use App\Support\CardholderName;
use App\Support\PlatformCredentials;
use App\Support\VendorLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The one place this application opens a socket to Authorize.Net.
 *
 * Everything here was read from the vendor's live documentation on
 * **2026-08-11** rather than from memory, which is `CLAUDE.md`'s standing rule
 * after 255, 277, 684 and 1349. What was verified, and what it changes:
 *
 * ## One URL for every request, and the operation lives in the body
 *
 * `https://api.authorize.net/xml/v1/request.api` in production and
 * `https://apitest.authorize.net/xml/v1/request.api` in sandbox, both HTTP POST.
 * There is no path per operation: the top-level key of the JSON object is the
 * request name — `createCustomerProfileRequest`, `ARBCreateSubscriptionRequest`
 * — and that key is the routing.
 *
 * ⚠️ **THE ENDPOINT IS `/xml/v1/request.api` EVEN FOR JSON.** It is not a typo
 * and there is no `/json/` sibling; a from-memory implementation that "corrects"
 * the path gets an authentication error (`E00007`) rather than a 404, which
 * sends whoever is debugging it to the credentials.
 *
 * ## ⚠️ It answers 200 for failures, which is the shape everything here defends against
 *
 * The outcome is `messages.resultCode` — `Ok` or `Error` — inside a 200 body.
 * `$response->failed()`, which is what {@see StripeApi} correctly uses for a
 * vendor with HTTP semantics, would read a declined card and a wrong API login
 * as successes. See {@see AuthorizeNetRequestFailed}.
 *
 * ## ⚠️ Its JSON carries a UTF-8 byte-order mark
 *
 * A long-standing documented quirk, in violation of RFC 7159's "implementations
 * MUST NOT add a byte order mark". `json_decode()` returns null on it with no
 * useful error, and the community's own diagnosis of that symptom is *"E00007,
 * check your credentials"* — so an implementation that does not strip it fails
 * every call while pointing at the wrong cause. Stripped in {@see self::decode()}.
 *
 * ## ⚠️ There is no idempotency key, and this is the largest gap against §3 rail 1
 *
 * Stripe takes an `Idempotency-Key` header and replays the original response for
 * 24 hours. **Authorize.Net has no equivalent.** What it has is `refId` — an
 * opaque merchant reference, echoed back and *not* deduplicated — 20 characters
 * as this client sends it, for the reason on {@see self::chargeCustomerProfile()} —
 * and a "duplicate window" setting that suppresses an identical *transaction*
 * within a configurable number of seconds. Neither is an idempotency key:
 * `refId` guarantees nothing and the duplicate window keys on the transaction's
 * own fields rather than on an intent we choose.
 *
 * **So idempotency on this gateway is ours, above the vendor, and it is
 * structural rather than a header** (decision 2137): a business may hold at most
 * one customer profile and one subscription, both enforced by unique keys in our
 * own schema and checked inside a transaction before the call is made. That is
 * strictly weaker than Stripe's guarantee — a response lost in flight leaves a
 * profile at the vendor that our row does not know about — so
 * {@see AuthorizeNetGateway} recovers rather than retries: it asks for the
 * existing profile by merchant id instead of creating a second one. **`refId`
 * carries our business id precisely so that recovery is possible by hand.**
 *
 * ## Card data never reaches this class
 *
 * Every payment is an `opaqueData` nonce produced by Accept.js in the browser
 * (`dataDescriptor` `COMMON.ACCEPT.INAPP.PAYMENT`, `dataValue` the token, valid
 * 15 minutes). SAQ-A is preserved on both gateways — decision 2056 — and there
 * is no method here that takes a card number, deliberately: a parameter that
 * does not exist cannot be filled in by somebody in a hurry.
 */
final class AuthorizeNetApi
{
    /**
     * ⚠️ LITERALS, DELIBERATELY, AND NOT CONFIG KEYS.
     *
     * The outbound-host lint reads host literals out of the files on its
     * permitted list and compares them against `docs/SUBPROCESSOR-INVENTORY.md`
     * (428–433). A base URL supplied from `.env` would not be scanned, and this
     * vendor would then be reachable without ever being named — which is the
     * failure that list exists to prevent.
     */
    private const string PRODUCTION = 'https://api.authorize.net/xml/v1/request.api';

    private const string SANDBOX = 'https://apitest.authorize.net/xml/v1/request.api';

    /**
     * The vendor's own name for a nonce produced by Accept.js.
     *
     * Verified against the live Accept.js documentation (2026-08-11). It is a
     * fixed string and not a format — there is exactly one value for a payment
     * nonce — so it belongs here rather than being passed in from a browser
     * payload that anybody can shape.
     */
    public const string OPAQUE_DATA_DESCRIPTOR = 'COMMON.ACCEPT.INAPP.PAYMENT';

    /**
     * The two `interval.unit` values the vendor's schema defines.
     *
     * `ARBSubscriptionUnitEnum` in `AnetApiSchema.xsd` has exactly these two
     * members (read from the vendor's own schema, 2026-08-12). They are constants
     * rather than a PHP enum because they are a wire format belonging to one
     * request, not a state this application stores — `CLAUDE.md`'s enum rule is
     * about columns, and `App\Enums\BillingTerm` is the enum that models the
     * *choice* these express.
     */
    public const string INTERVAL_DAYS = 'days';

    public const string INTERVAL_MONTHS = 'months';

    /**
     * The merchant credential every outbound call on this gateway signs with.
     *
     * ⚠️ **NAMED ONCE SO THE GUARD AND THE READ CANNOT DRIFT ONTO TWO
     * LITERALS** — `TurnstileVerifier::SECRET_KEY`'s and
     * `IndexingApi::CREDENTIAL`'s house style, and the shape
     * `Architecture\CredentialsTest`'s census resolves, so a key added here
     * without a guard in front of it moves that list.
     */
    public const string API_LOGIN_ID = 'authorize_net_api_login_id';

    public const string TRANSACTION_KEY = 'authorize_net_transaction_key';

    /**
     * The browser half — rendered into the card page so Accept.js can tokenise.
     *
     * ⛔ **THIS CLASS NEVER READS IT AND DECLARES IT ANYWAY, WHICH IS THE POINT
     * OF 9295.** It is declared here because {@see self::checkoutIsConfigured()}
     * is where the two halves are put together, and putting them together in one
     * place is what stops a door guard covering a *subset* of the keys its path
     * needs. {@see self::OPAQUE_DATA_DESCRIPTOR} is already an Accept.js
     * constant on this class for the same reason: what belongs here is this
     * vendor's integration, not this class's HTTP body.
     */
    public const string PUBLIC_CLIENT_KEY = 'authorize_net_public_client_key';

    /**
     * Whether an outbound call on this gateway can be made at all.
     *
     * ⛔ **THE GUARD THAT READ TWO KEYS OF THE FOUR ITS PATH NEEDED — 9295.**
     * `AuthorizeNetCheckoutController::create()` and `Account\Plan::cardPanel()`
     * each checked {@see self::API_LOGIN_ID} and {@see self::PUBLIC_CLIENT_KEY}
     * — the two Accept.js keys — and **nothing anywhere guarded
     * {@see self::TRANSACTION_KEY}, which is what {@see self::send()} signs
     * with**. With the first two pasted and the third missed — one paste apart
     * in Ops, and `CredentialManifest`'s own entry says the transaction and
     * signature keys "are routinely swapped" — the card form rendered, Accept.js
     * tokenised a **real card**, the Automatic Renewal Law acknowledgement was
     * written, and the person was bounced back to `/billing` with no message.
     *
     * ⛔ **`CLAUDE.md`'s door guard reading a proxy for the column the constraint
     * reads**, and the reason the fix is a derivation rather than a third
     * literal: {@see self::checkoutIsConfigured()} *calls this method*, so a
     * door's key set can never again be a subset of the call's.
     */
    public static function isConfigured(): bool
    {
        return PlatformCredentials::has(self::API_LOGIN_ID)
            && PlatformCredentials::has(self::TRANSACTION_KEY);
    }

    /**
     * The same question for the surfaces that render the card form.
     *
     * ⚠️ **THE BROWSER HALF IS AN ADDITION TO THE CALL'S SET AND NEVER A SET OF
     * ITS OWN.** A page that tokenises a card is a page that is about to post it
     * here, so a form drawn on the strength of the public client key alone is a
     * form that collects a card it cannot spend.
     */
    public static function checkoutIsConfigured(): bool
    {
        return self::isConfigured()
            && PlatformCredentials::has(self::PUBLIC_CLIENT_KEY);
    }

    /**
     * Create a customer profile with a payment profile on it, from a nonce.
     *
     * ⚠️ **`merchantCustomerId` IS THE BUSINESS ID AND IT IS WHAT MAKES RECOVERY
     * POSSIBLE.** Without an idempotency key, a lost response is only
     * recoverable if the object we may have created is findable by something we
     * already know — and `getCustomerProfileRequest` accepts a
     * `merchantCustomerId`. Sending a UUID here instead would be tidier and would
     * make the same lost response unrecoverable.
     *
     * ⚠️ `validationMode` is `testMode` in sandbox and `liveMode` in production.
     * `liveMode` runs a real zero-dollar (or $0.01, per card brand) validation
     * against the card, which is what catches a nonce that tokenised fine and
     * belongs to a card that will decline — the failure we would otherwise meet
     * at the first real charge, weeks later, with the customer gone.
     *
     * ⛔ **THIS IS ONE OF TWO CREATORS AND IT IS THE ONE THE FIRST-EVER PURCHASE
     * GOES THROUGH.** {@see self::createPaymentProfile()} is the other, and
     * fixing either alone leaves the other broken while the suite looks covered
     * — the pre-existing happy-path fixture reaches only this one.
     *
     * @return array{customerProfileId: string, customerPaymentProfileId: string}
     *
     * @throws AuthorizeNetRequestFailed
     */
    public function createCustomerProfile(
        int $businessId,
        string $businessName,
        string $email,
        string $opaqueDataValue,
        CardholderName $cardholder,
    ): array {
        $body = $this->send('createCustomerProfileRequest', [
            'profile' => [
                'merchantCustomerId' => (string) $businessId,

                // ⚠️ THE DESCRIPTION IS THE BUSINESS NAME AND NOTHING ELSE.
                // `SUBPROCESSOR-INVENTORY` §1 records what Stripe receives and
                // the same sentence has to stay true here: a business name, our
                // price, and an id. Never an end customer's anything.
                'description' => $businessName,
                'email' => $email,
                'paymentProfiles' => [
                    'customerType' => 'business',

                    // ⛔ **`billTo` BEFORE `payment`, AND THE ORDER IS THE
                    // VENDOR'S `xs:sequence` RATHER THAN A STYLE CHOICE.**
                    // `customerPaymentProfileType` extends
                    // `customerPaymentProfileBaseType`, whose sequence is
                    // `customerType` then `billTo`; `payment` is the first
                    // element of the extension and therefore comes after both
                    // (`AnetApiSchema.xsd`, re-read from the sandbox and the
                    // production host on 2026-08-29). Appending it after
                    // `payment` is an `E00003` on a request that is otherwise
                    // perfect — **and this controller's `refusal()` reports an
                    // E00003 to the buyer as a card that was declined.**
                    //
                    // ⛔ **AND WITHOUT IT THE `ARBCreateSubscriptionRequest`
                    // THAT FOLLOWS ANSWERS `E00014`** — *"Bill-To First Name is
                    // required."* — which is why no tenant has ever completed a
                    // checkout. It cannot be moved onto the subscription
                    // instead: that is `E00093`, *"PaymentProfile cannot be
                    // sent with billing data."* Both proven by live probes
                    // against the sandbox.
                    'billTo' => $cardholder->billTo(),
                    'payment' => [
                        'opaqueData' => [
                            'dataDescriptor' => self::OPAQUE_DATA_DESCRIPTOR,
                            'dataValue' => $opaqueDataValue,
                        ],
                    ],
                ],
            ],
            'validationMode' => $this->isProduction() ? 'liveMode' : 'testMode',
        ], $businessId);

        $profileId = $this->stringAt($body, 'customerProfileId');

        // `customerPaymentProfileIdList` is a list even when one profile was
        // created, and reading `[0]` off it is the documented shape. Anything
        // else means the profile exists with no payment method on it, which
        // would produce a subscription create that fails for a reason naming
        // neither call.
        $paymentProfiles = $body['customerPaymentProfileIdList'] ?? null;
        $paymentProfileId = is_array($paymentProfiles) && isset($paymentProfiles[0]) && is_string($paymentProfiles[0])
            ? $paymentProfiles[0]
            : null;

        if ($profileId === null || $paymentProfileId === null) {
            throw AuthorizeNetRequestFailed::unreadable();
        }

        return [
            'customerProfileId' => $profileId,
            'customerPaymentProfileId' => $paymentProfileId,
        ];
    }

    /**
     * Add a replacement payment method to an existing profile.
     *
     * The dunning path: a card expired, the tenant supplied a new nonce, and the
     * profile they already have takes another payment profile rather than the
     * whole customer being recreated.
     *
     * ⛔ **AND IT IS ALSO EVERY SUBSEQUENT SIGNUP ATTEMPT.**
     * {@see AuthorizeNetGateway::resolveProfile()} reaches this method whenever a
     * customer profile already exists — the tenant whose first card was
     * declined, and the tenant whose create response was lost — so a `billTo`
     * fix confined to {@see self::createCustomerProfile()} repairs the first
     * purchase and leaves every retry answering `E00014`. **The two creators are
     * one defect and neither is the whole of it.**
     *
     * @throws AuthorizeNetRequestFailed
     */
    public function createPaymentProfile(
        int $businessId,
        string $customerProfileId,
        string $opaqueDataValue,
        CardholderName $cardholder,
    ): string {
        $body = $this->send('createCustomerPaymentProfileRequest', [
            'customerProfileId' => $customerProfileId,
            'paymentProfile' => [
                'customerType' => 'business',

                // ⛔ **`billTo` BEFORE `payment` — the same `xs:sequence`, the
                // same request type the probe proved.** A
                // `createCustomerPaymentProfileRequest` carrying a `billTo` of
                // first + last **only** was accepted by this merchant and the
                // card was validated; the subscription that then points at the
                // profile is what would otherwise answer `E00014`.
                'billTo' => $cardholder->billTo(),
                'payment' => [
                    'opaqueData' => [
                        'dataDescriptor' => self::OPAQUE_DATA_DESCRIPTOR,
                        'dataValue' => $opaqueDataValue,
                    ],
                ],
            ],
            'validationMode' => $this->isProduction() ? 'liveMode' : 'testMode',
        ], $businessId);

        $id = $this->stringAt($body, 'customerPaymentProfileId');

        if ($id === null) {
            throw AuthorizeNetRequestFailed::unreadable();
        }

        return $id;
    }

    /**
     * The customer profile already recorded against this business, if any.
     *
     * ⚠️ **THIS IS THE RECOVERY HALF OF THE MISSING IDEMPOTENCY KEY.** A create
     * whose response was lost leaves a profile at the vendor and no row here; the
     * next attempt asks this first and adopts what it finds, rather than minting
     * a second profile that the first one's subscription is not attached to.
     * Returns null when the vendor says it does not exist, and only then —
     * every other error still throws.
     *
     * @throws AuthorizeNetRequestFailed
     */
    public function findCustomerProfileByMerchantId(int $businessId): ?string
    {
        try {
            $body = $this->send('getCustomerProfileRequest', [
                'merchantCustomerId' => (string) $businessId,
            ], $businessId);
        } catch (AuthorizeNetRequestFailed $e) {
            // E00040 is "The record cannot be found", which is the ordinary
            // answer for a business that has never paid. Anything else is a
            // real failure and keeps its exception.
            if ($e->reason === 'E00040') {
                return null;
            }

            throw $e;
        }

        $profile = is_array($body['profile'] ?? null) ? $body['profile'] : [];

        return $this->stringAt($profile, 'customerProfileId');
    }

    /**
     * Create an ARB subscription against a stored profile.
     *
     * ⚠️ **THE TRIAL IS A FUTURE `startDate`, NOT `trialOccurrences` (decision
     * 2138), AND THIS IS THE ONE PLACE THE TWO GATEWAYS GENUINELY CANNOT BE MADE
     * TO MATCH.** ARB expresses a trial as a whole number of *occurrences of the
     * same interval* at a reduced amount — so with decision 147's 30-day cycle,
     * the shortest trial ARB can describe is 30 days, and the trial this product
     * sells is 14 (2065). Setting `startDate` to today + `billing.trial_days`
     * and charging nothing until then is exactly a 14-day trial, and it needs no
     * trial fields at all. The consequence to know: **the vendor has no idea a
     * trial is running**, so `trialing` on our row is derived from that date and
     * is the one status a webhook can never tell us.
     *
     * ⚠️ **`interval` CANNOT BE CHANGED AFTER CREATION** — the vendor's own
     * documentation says `paymentSchedule.interval.length` and `.unit` "may not
     * be updated". Decision 147's 30-day cycle is therefore fixed at creation for
     * the life of a subscription, and moving a tenant between monthly and annual
     * means cancelling and recreating. Verified rather than assumed, because the
     * obvious implementation of a plan change is an update call that this vendor
     * silently will not honour.
     *
     * ⚠️ `interval.unit` is `days` or `months` — **7 to 365 for days, 1 to 12
     * for months** (re-read from the vendor's API reference 2026-08-12, for the
     * annual term decision 2680 adds). 30 is inside the first; a shorter cycle
     * than a week is not expressible on this gateway.
     *
     * ## The instalment plan is the vendor's own trial mechanism (decision 2680)
     *
     * ⚠️ **AND THAT IS NOT THE FREE TRIAL, WHICH IS STILL A FUTURE `startDate`.**
     * Two different things wear the vendor's word "trial" on this call and
     * conflating them is the mistake to expect here. Read from the reference on
     * 2026-08-12, verbatim: *"totalOccurrences — Number of payments for the
     * subscription. If a trial period is specified, this value should include the
     * number of payments during the trial period"*, *"trialOccurrences — Number
     * of payments in the trial period"*, and *"During the trial period, we will
     * bill trialAmount on each scheduled payment. Once the trial period is over,
     * we will bill amount for the remaining scheduled payments."*
     *
     * So `$totalOccurrences = 3`, `$trialOccurrences = 2`, `trialAmount` = the
     * smaller payment and `amount` = the larger is exactly 2055's three-payment
     * annual — **and it is expressible only because the remainder rides the
     * *final* payment.** `CLAUDE.md`'s ordering puts the larger payment last,
     * which is the one the vendor bills "for the remaining scheduled payments";
     * decision 543's ordering, with the remainder first, cannot be expressed as a
     * single ARB subscription at all. 2135 records that the record disagrees with
     * itself about that ordering — this is a fact about the vendor, not a vote.
     *
     * @param  int  $amountMinorUnits  Integer cents. Formatted to the decimal
     *                                 string the vendor's `amount` field takes
     *                                 exactly once, in {@see self::amount()}.
     * @param  int  $trialOccurrences  How many of `$totalOccurrences` are billed
     *                                 at `$trialAmountMinorUnits` instead. Zero
     *                                 leaves both trial fields off the payload.
     *
     * @throws AuthorizeNetRequestFailed
     */
    public function createSubscription(
        int $businessId,
        string $name,
        int $amountMinorUnits,
        int $intervalLength,
        string $intervalUnit,
        string $startDate,
        int $totalOccurrences,
        string $customerProfileId,
        string $customerPaymentProfileId,
        int $trialOccurrences = 0,
        ?int $trialAmountMinorUnits = null,
    ): string {
        $this->assertIntervalIsExpressible($intervalLength, $intervalUnit);

        $schedule = [
            'interval' => [
                'length' => $intervalLength,
                'unit' => $intervalUnit,
            ],
            'startDate' => $startDate,
            'totalOccurrences' => $totalOccurrences,
        ];

        $subscription = [
            // The vendor caps this at 50 characters and truncates silently
            // past it, which would make two tenants' subscriptions
            // indistinguishable on the merchant interface.
            'name' => mb_substr($name, 0, 50),
            'paymentSchedule' => $schedule,
            'amount' => $this->amount($amountMinorUnits),
        ];

        if ($trialOccurrences > 0) {
            if ($trialAmountMinorUnits === null || $trialOccurrences >= $totalOccurrences) {
                // Both halves would be accepted by the vendor and neither is what
                // anybody meant: a trial with no amount bills `amount` for every
                // payment, and a trial as long as the subscription bills the
                // *smaller* amount for every payment. Each is a wrong bill that
                // looks like a successful create.
                throw new RuntimeException(
                    'An instalment schedule needs a trial amount and at least one payment '
                    .'outside the trial; otherwise the vendor bills the wrong figure for '
                    .'every occurrence.'
                );
            }

            // ⚠️ THE ORDER OF THESE KEYS IS THE VENDOR'S SCHEMA ORDER, NOT A
            // STYLE CHOICE. Authorize.Net's JSON is a projection of the XSD, and
            // its parser follows the `xs:sequence` — `trialOccurrences` after
            // `totalOccurrences` inside the schedule, `trialAmount` after
            // `amount` on the subscription. Alphabetising them is an E00003 on a
            // request that is otherwise perfect.
            $subscription['paymentSchedule']['trialOccurrences'] = $trialOccurrences;
            $subscription['trialAmount'] = $this->amount($trialAmountMinorUnits);
        }

        $subscription['profile'] = [
            'customerProfileId' => $customerProfileId,
            'customerPaymentProfileId' => $customerPaymentProfileId,
        ];

        sleep(10); $body = $this->send('ARBCreateSubscriptionRequest', [
            'subscription' => $subscription,
        ], $businessId);

        $id = $this->stringAt($body, 'subscriptionId');

        if ($id === null) {
            throw AuthorizeNetRequestFailed::unreadable();
        }

        return $id;
    }

    /**
     * ⚠️ The interval ranges, refused here rather than at the vendor.
     *
     * Both are the vendor's, re-read from its API reference on 2026-08-12: *"For
     * a unit of days, use an integer between 7 and 365, inclusive"* and *"For a
     * unit of months, use an integer between 1 and 12, inclusive"*. Refusing here
     * matters because the vendor's own refusal is an error code naming a field
     * rather than the rule behind it, and because `interval` **cannot be changed
     * after creation** — a subscription created with a wrong one is cancelled and
     * recreated, not fixed.
     */
    private function assertIntervalIsExpressible(int $length, string $unit): void
    {
        if ($unit === self::INTERVAL_DAYS) {
            $withinRange = $length >= 7 && $length <= 365;
        } elseif ($unit === self::INTERVAL_MONTHS) {
            $withinRange = $length >= 1 && $length <= 12;
        } else {
            throw new RuntimeException(
                "Authorize.Net expresses a recurring interval in days or months, not [{$unit}]."
            );
        }

        if (! $withinRange) {
            throw new RuntimeException(
                "An interval of {$length} {$unit} is outside what Authorize.Net accepts "
                .'(7 to 365 days, or 1 to 12 months).'
            );
        }
    }

    /**
     * Charge a stored customer profile once — the top-up (decisions 3301–3303).
     *
     * ⛔ **A ONE-OFF CHARGE IS A COMPLETELY DIFFERENT REQUEST TYPE FROM ARB, AND
     * READING THE SUBSCRIPTION CODE ABOVE FOR ITS SHAPE IS THE MISTAKE TO
     * EXPECT.** Verified against the vendor's live API reference — "Charge a
     * Customer Profile" — read **2026-08-14**, rather than inferred from the
     * neighbouring `ARBCreateSubscriptionRequest`:
     *
     *   request        `createTransactionRequest`, not `ARB…`.
     *   type           `transactionRequest.transactionType` =
     *                  `authCaptureTransaction` — authorise and capture in one
     *                  step. An `authOnlyTransaction` would take nothing until a
     *                  second call nobody makes.
     *   stored card    `transactionRequest.profile.customerProfileId` and
     *                  `profile.paymentProfile.paymentProfileId`. **Note the
     *                  nesting**: the payment profile is an object under
     *                  `paymentProfile`, not a sibling id — ARB's own
     *                  `subscription.profile` puts both ids flat, one level up,
     *                  and copying that shape is an `E00003` on a request that is
     *                  otherwise perfect.
     *   our handle     `refId`, echoed back — **and echoed into the webhook as
     *                  `payload.merchantReferenceId`**, which is how a
     *                  notification is matched to a purchase. ⚠️ **20 characters
     *                  by the vendor's API reference and 50 by its own
     *                  `AnetApiSchema.xsd`** — see the length note on
     *                  {@see self::chargeCustomerProfile()}.
     *   response       `transactionResponse.transId` and
     *                  `transactionResponse.responseCode` — `1` approved, `2`
     *                  declined, `3` error, `4` held for review.
     *
     * ⚠️ **`responseCode` IS CHECKED AS WELL AS `messages.resultCode`, AND THAT IS
     * NOT BELT AND BRACES.** This vendor's 200-is-not-success problem has a second
     * floor on this request: the envelope can say `Ok` while the transaction
     * inside it was **declined** or **held for review**. A client that read only
     * the envelope would record a transaction id for a charge that took no money,
     * and the tenant would be credited by the settlement that followed.
     *
     * ⚠️ **`4` — HELD FOR REVIEW — IS TREATED AS NOT-PAID AND THAT IS A CHOICE.**
     * The money may yet be captured by a merchant releasing it in the vendor's
     * interface, at which point the webhook arrives and settles the purchase
     * normally. What must not happen is this method reporting success for money
     * that has not moved.
     *
     * ⚠️ **THERE IS STILL NO IDEMPOTENCY KEY ON THIS GATEWAY** (see the class
     * docblock), so a lost response on a *charge* is the worst case in this
     * integration. What guards it is the vendor's own **duplicate window**, which
     * suppresses an identical transaction within a configurable number of seconds
     * — and our own `refId` being unique per attempt, so a human can reconcile.
     * Neither is an idempotency key and neither is claimed to be.
     *
     * @param  int  $amountMinorUnits  Integer cents, formatted to the vendor's
     *                                 decimal exactly once in {@see self::amount()}.
     * @param  string  $reference  Our own handle, and **one string filling two
     *                             fields** — `refId` and `order.invoiceNumber`.
     *                             ⛔ **20, AND THE VENDOR'S TWO ARTEFACTS
     *                             DISAGREE ABOUT WHICH FIELD THAT IS.**
     *                             `AnetApiSchema.xsd` (fetched 2026-08-29 from
     *                             both hosts, byte-identical) gives
     *                             `ANetApiRequest.refId` `maxLength 50` and
     *                             `orderType.invoiceNumber` `maxLength 20`; the
     *                             vendor's live API reference says *"String, up
     *                             to 20 characters"* for **both**. ⚠️ **The
     *                             guard below is correct under either reading
     *                             because the same string goes to both fields**
     *                             — what this docblock used to claim, that the
     *                             20 was `refId`'s, is the weaker of the two
     *                             sources and is the half a reader would have
     *                             checked. ⚠️ **That the vendor truncates
     *                             SILENTLY rather than refusing is unverified
     *                             by us either way**, which is exactly why this
     *                             refuses rather than trimming.
     * @return string The vendor's transaction id.
     *
     * @throws AuthorizeNetRequestFailed
     */
    public function chargeCustomerProfile(
        int $businessId,
        string $customerProfileId,
        string $customerPaymentProfileId,
        int $amountMinorUnits,
        string $reference,
        string $description,
    ): string {
        if (mb_strlen($reference) > 20) {
            throw new RuntimeException(
                'An Authorize.Net reference is 20 characters — the limit its schema '
                .'puts on order.invoiceNumber and its API reference puts on refId, and '
                .'this one string fills both. A truncated reference comes back on the '
                .'webhook as a handle that matches no purchase, so the money moves and '
                .'nothing is credited.'
            );
        }

        $body = $this->send('createTransactionRequest', [
            // ⚠️ THE KEY ORDER IS THE VENDOR'S XSD SEQUENCE, NOT A STYLE CHOICE —
            // the same constraint `createSubscription()` records. Alphabetising
            // these is an E00003.
            'transactionRequest' => [
                'transactionType' => 'authCaptureTransaction',
                'amount' => $this->amount($amountMinorUnits),
                'profile' => [
                    'customerProfileId' => $customerProfileId,
                    'paymentProfile' => [
                        'paymentProfileId' => $customerPaymentProfileId,
                    ],
                ],
                'order' => [
                    // ⚠️ THE INVOICE NUMBER IS *NOT* THE CORRELATION HANDLE. The
                    // vendor's own sample notification payload carries
                    // `merchantReferenceId` and no `invoiceNumber` at all, so a
                    // design that matched on this field would resolve nothing.
                    // It is here because it is what a merchant sees on their own
                    // reporting screens.
                    'invoiceNumber' => $reference,
                    // The SKU, never a price: the amount is on the same receipt
                    // as a number, and a second copy in words can disagree with
                    // it after a price change (512's shape).
                    'description' => mb_substr($description, 0, 255),
                ],
            ],
        ], $businessId, $reference);

        $transaction = is_array($body['transactionResponse'] ?? null) ? $body['transactionResponse'] : [];

        $responseCode = $transaction['responseCode'] ?? null;

        // The vendor returns this as a JSON number in some responses and a string
        // in others — the same normalisation `stringAt()` makes for ids, and for
        // the same reason: neither is arithmetic.
        if (! in_array((string) (is_int($responseCode) || is_string($responseCode) ? $responseCode : ''), ['1'], true)) {
            $errors = is_array($transaction['errors'] ?? null) ? $transaction['errors'] : [];
            $codes = [];

            foreach ($errors as $error) {
                if (is_array($error) && (is_string($error['errorCode'] ?? null) || is_int($error['errorCode'] ?? null))) {
                    // The code, never `errorText`: that field quotes the value it
                    // rejected, including a card's last four.
                    $codes[] = (string) $error['errorCode'];
                }
            }

            $failure = AuthorizeNetRequestFailed::fromResult(
                $codes === [] ? ['response_code_'.(is_scalar($responseCode) ? (string) $responseCode : 'absent')] : $codes
            );

            VendorLog::failure('authorize_net', 'POST', $this->url(), $failure->reason, $businessId);

            throw $failure;
        }

        $id = $this->stringAt($transaction, 'transId');

        if ($id === null) {
            // ⛔ AN APPROVED CHARGE WE CANNOT NAME. The money moved and we have no
            // handle to reconcile or refund it with, which is the one failure on
            // this path that a person has to be told about — `unreadable` is what
            // the caller turns into a failed purchase carrying this reason.
            throw AuthorizeNetRequestFailed::unreadable();
        }

        return $id;
    }

    /**
     * The vendor's own view of a subscription's status.
     *
     * ⚠️ **THE RECONCILIATION READ, AND IT IS NOT OPTIONAL ON THIS GATEWAY.**
     * Authorize.Net publishes no webhook retry schedule, so a missed delivery is
     * not guaranteed to be re-sent the way Stripe's three days of retries are.
     * Webhooks stay the source of truth (2056); this is what notices when one
     * never arrived.
     *
     * @throws AuthorizeNetRequestFailed
     */
    public function subscriptionStatus(int $businessId, string $subscriptionId): ?string
    {
        $body = $this->send('ARBGetSubscriptionStatusRequest', [
            'subscriptionId' => $subscriptionId,
        ], $businessId);

        return $this->stringAt($body, 'status');
    }

    /**
     * What the vendor now says about one transaction (decision 3483's sweep).
     *
     * ⛔ **`getTransactionDetailsRequest`, AND IT IS THE ONLY WAY TO ASK.** Read
     * from the vendor's live API reference and from its own `AnetApiSchema.xsd`
     * on **2026-08-14**, rather than from the notification handler that normally
     * answers this question:
     *
     *   request   `getTransactionDetailsRequest` with one field, `transId` — the
     *             id recorded from the synchronous charge. **There is no lookup by
     *             `refId`**, so a purchase whose charge response was lost has no
     *             transaction id and cannot be asked about on this gateway at all.
     *   response  `transaction.transactionStatus` (the vendor's own word),
     *             `transaction.responseCode` (`1` approved, `2` declined, `3`
     *             error, `4` held for review) and **two** amounts.
     *
     * ⚠️ **`transactionStatus` AND `responseCode` ARE NOT THE SAME QUESTION, AND
     * NEITHER ALONE MEANS "PAID".** `responseCode` is what the processor said at
     * authorisation and never changes; `transactionStatus` is where the money is
     * now. A transaction can be approved (`1`) and `voided`, or approved and
     * `settlementError`. {@see App\Services\Billing\PurchaseReconciliation} demands
     * both, which is 3449's two-floor check applied to a read instead of a write.
     *
     * ⛔ **`authAmount` AND `settleAmount` ARE DECIMALS WITH FOUR FRACTIONAL
     * DIGITS**, verbatim from the XSD: *"decimal element with minimum inclusive
     * value of 0.00 and 4 fractional digits"*. The notification payload's own
     * `authAmount` is two, so a converter copied from {@see AuthorizeNetWebhooks}
     * would refuse `45.0000` outright — and one that "fixed" that by multiplying a
     * float would be 3452's IEEE-754 hole. The conversion lives with the caller
     * and is a string operation there.
     *
     * ⚠️ **NULL MEANS NO READABLE TRANSACTION** — either the vendor says the record
     * cannot be found (E00040, `findCustomerProfileByMerchantId()`'s shape
     * exactly) or an `Ok` body carried no `transaction` object. Both are *"we
     * learned nothing"*, which the caller treats as ambiguous and never as
     * "not paid". Every other failure still throws, because "we could not ask" and
     * "there is no such charge" must not collapse into one answer on the vendor
     * that took the money.
     *
     * @return array<string, mixed>|null The `transaction` object, or null.
     *
     * @throws AuthorizeNetRequestFailed
     */
    public function transactionDetails(int $businessId, string $transactionId): ?array
    {
        try {
            $body = $this->send('getTransactionDetailsRequest', [
                'transId' => $transactionId,
            ], $businessId);
        } catch (AuthorizeNetRequestFailed $e) {
            if ($e->reason === 'E00040') {
                return null;
            }

            throw $e;
        }

        return is_array($body['transaction'] ?? null) ? $body['transaction'] : null;
    }

    /**
     * Cancel a subscription.
     *
     * ⚠️ **THE VENDOR SAYS A CANCELLED SUBSCRIPTION "CANNOT BE REACTIVATED".**
     * There is no undo and no equivalent of Stripe's `cancel_at_period_end`, so
     * anything calling this has already decided, and `CLAUDE.md`'s CONFIRM rule
     * covers the screen that offers it.
     *
     * @throws AuthorizeNetRequestFailed
     */
    public function cancelSubscription(int $businessId, string $subscriptionId): void
    {
        $this->send('ARBCancelSubscriptionRequest', [
            'subscriptionId' => $subscriptionId,
        ], $businessId);
    }

    /**
     * Point an existing subscription at a different payment profile.
     *
     * The dunning remedy: the card was replaced, and the subscription has to be
     * told. `interval` is deliberately absent from this payload — the vendor
     * will not update it, and sending it invites the belief that it works.
     *
     * @throws AuthorizeNetRequestFailed
     */
    public function updateSubscriptionPaymentProfile(
        int $businessId,
        string $subscriptionId,
        string $customerProfileId,
        string $customerPaymentProfileId,
    ): void {
        $this->send('ARBUpdateSubscriptionRequest', [
            'subscriptionId' => $subscriptionId,
            'subscription' => [
                'profile' => [
                    'customerProfileId' => $customerProfileId,
                    'customerPaymentProfileId' => $customerPaymentProfileId,
                ],
            ],
        ], $businessId);
    }

    /**
     * Post one request and return its body, or throw.
     *
     * @param  array<string, mixed>  $payload
     * @param  ?string  $refId  A caller-supplied correlation handle, or null for a
     *                          generated one. ⚠️ **Supplied by exactly one caller
     *                          and for one reason**: on a charge, `refId` comes
     *                          back on the webhook as `payload.merchantReferenceId`
     *                          and is how the notification finds the purchase — so
     *                          it has to be *ours* rather than random. Every other
     *                          request here is correlated by an object id we store,
     *                          and a random handle is right for those.
     * @return array<string, mixed>
     *
     * @throws AuthorizeNetRequestFailed
     */
    private function send(string $request, array $payload, int $businessId, ?string $refId = null): array
    {
        $url = $this->url();

        $this->assertConfigured($url, $businessId);

        $envelope = [
            $request => [
                'merchantAuthentication' => [
                    'name' => PlatformCredentials::get(self::API_LOGIN_ID),
                    'transactionKey' => PlatformCredentials::get(self::TRANSACTION_KEY),
                ],
                // ⚠️ 20 CHARACTERS, ECHOED BACK, AND DEDUPLICATED BY NOTHING.
                // It is a correlation handle for a human reconciling a lost
                // response, which is the recovery this gateway leaves to us —
                // never an idempotency key. See the class docblock.
                'refId' => mb_substr(
                    $refId ?? 'b'.$businessId.'-'.substr(bin2hex(random_bytes(8)), 0, 10),
                    0,
                    20,
                ),
                ...$payload,
            ],
        ];

        try {
            $response = VendorLog::timed(
                'authorize_net',
                'POST',
                $url,
                fn (): Response => Http::withHeaders(['Content-Type' => 'application/json'])
                    ->timeout((int) config('services.authorizenet.timeout', 15))
                    ->withBody(json_encode($envelope, JSON_THROW_ON_ERROR), 'application/json')
                    ->post($url),
                $businessId,
            );
        } catch (ConnectionException $e) {
            // The class name, never the message: a connection exception's
            // message carries the full request URI (VendorLog's rule).
            VendorLog::failure('authorize_net', 'POST', $url, $e::class, $businessId);

            throw AuthorizeNetRequestFailed::unreachable($e::class);
        }

        if ($response->failed()) {
            // A genuine transport-level failure — a 502 from something in front
            // of the vendor, or their own outage. Distinct from the 200-with-an
            // -error the next block handles.
            VendorLog::failure('authorize_net', 'POST', $url, 'http_'.$response->status(), $businessId);

            throw AuthorizeNetRequestFailed::unreachable('http_'.$response->status());
        }

        $body = $this->decode($response->body());

        if ($body === null) {
            VendorLog::failure('authorize_net', 'POST', $url, 'unreadable_response', $businessId);

            throw AuthorizeNetRequestFailed::unreadable();
        }

        $this->assertOk($body, $url, $businessId);

        return $body;
    }

    /**
     * Refuse before the envelope is built, in this class's own vocabulary.
     *
     * ⛔ **THE READ WAS OUTSIDE THIS CLASS'S ERROR CLASSIFICATION AND THAT IS
     * THE WHOLE DEFECT — 9294.** The two `get()` calls in {@see self::send()}
     * sit above the `try`, so an unset key never became an
     * {@see AuthorizeNetRequestFailed} and every `catch` written against one
     * missed it: a 500 on `POST /account/plan/cancel`, a 500 on
     * `/account/credit`, and a messageless redirect out of both checkout
     * controllers. Classifying it here is what makes the callers that were
     * already written correctly start working.
     *
     * ⚠️ **THE ABSENCE IS RECORDED HERE AND NOWHERE ELSE**, exactly as
     * `GooglePlacesClient::isConfigured()` records it: from here down there is
     * no request to attribute it to, and *"a call that produced no usable
     * answer"* covers one that was never attempted for a reason worth a line.
     * ⚠️ **A fixed label and never which key** — the Ops credentials board is
     * the authority for that, and a reason string reaches a
     * `credit_purchases.failure_reason` an operator reads.
     *
     * ⛔ **AND IT DOES NOT REACH `PlatformHealthSignal::VendorCall`, WHICH IS
     * WHAT `OperatorAlertKind::VendorErrorRate` READS.** Nothing on this gateway
     * does — the only writer of that signal in the tree is the AI router (9235),
     * so **no billing failure of any kind rings a bell today**. That is 9297 and
     * it is owed to whoever holds `OperatorAlertKind`.
     *
     * @throws AuthorizeNetRequestFailed
     */
    private function assertConfigured(string $url, int $businessId): void
    {
        if (self::isConfigured()) {
            return;
        }

        VendorLog::failure('authorize_net', 'POST', $url, 'credential_missing', $businessId);

        throw AuthorizeNetRequestFailed::unconfigured();
    }

    /**
     * ⚠️ The BOM strip, and the reason it is not a nicety.
     *
     * See the class docblock: the vendor prefixes its JSON with a UTF-8
     * byte-order mark, `json_decode()` returns null on it, and the community's
     * standard diagnosis of that null is "check your credentials" — so this one
     * line is the difference between a working integration and a week spent on
     * the wrong problem.
     *
     * @return array<string, mixed>|null
     */
    private function decode(string $raw): ?array
    {
        $decoded = json_decode(ltrim($raw, "\u{FEFF}"), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * ⚠️ The 200-is-not-success check.
     *
     * @param  array<string, mixed>  $body
     *
     * @throws AuthorizeNetRequestFailed
     */
    private function assertOk(array $body, string $url, int $businessId): void
    {
        $messages = is_array($body['messages'] ?? null) ? $body['messages'] : [];

        // ⚠️ ABSENT IS NOT OK. A body with no resultCode is not a body this
        // client understands, and defaulting it to success would make every
        // shape change silently succeed.
        $resultCode = $messages['resultCode'] ?? null;

        if ($resultCode === 'Ok') {
            return;
        }

        $codes = [];

        foreach (is_array($messages['message'] ?? null) ? $messages['message'] : [] as $message) {
            if (is_array($message) && is_string($message['code'] ?? null)) {
                // The code, never `text`: that field quotes the value it
                // rejected. See AuthorizeNetRequestFailed.
                $codes[] = $message['code'];
            }
        }

        $failure = AuthorizeNetRequestFailed::fromResult($codes);

        VendorLog::failure('authorize_net', 'POST', $url, $failure->reason, $businessId);

        throw $failure;
    }

    /**
     * Integer minor units to the decimal string the vendor's `amount` takes.
     *
     * ⚠️ **THE ONLY PLACE IN THIS INTEGRATION WHERE MONEY STOPS BEING AN
     * INTEGER, AND IT PRODUCES A STRING RATHER THAN A FLOAT.** `18` §Money
     * handling and row 22's build-failing gate forbid money "stored or compared
     * as a float"; the vendor's wire format is a decimal, so a conversion is
     * unavoidable at the boundary. `intdiv` and `%` do it exactly, the same way
     * `PlanPricing::format()` does after decision 584 took a `/ 100` out of it.
     * A `number_format($cents / 100, 2)` here would put an IEEE-754 double on
     * the value that becomes a charge.
     */
    private function amount(int $minorUnits): string
    {
        if ($minorUnits < 0) {
            throw new RuntimeException('A subscription amount cannot be negative.');
        }

        return intdiv($minorUnits, 100).'.'.str_pad((string) ($minorUnits % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function stringAt(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;

        if (is_string($value) && $value !== '') {
            return $value;
        }

        // The vendor returns `subscriptionId` as a JSON number in some
        // responses and a string in others. Both are ids and neither is
        // arithmetic, so an integer is accepted and normalised rather than
        // being read as "absent" — which would throw `unreadable` on a call
        // that worked.
        return is_int($value) ? (string) $value : null;
    }

    /**
     * ⚠️ **THE TEST ENVIRONMENT CANNOT REACH PRODUCTION, AND IT IS ENFORCED HERE
     * RATHER THAN TRUSTED TO `Http::preventStrayRequests()`.**
     *
     * That guard refuses every unfaked request, which is the right default and
     * is already installed. What it cannot do is refuse a request that a test
     * *did* fake against the production host — and a fake is exactly what a
     * copy-pasted fixture supplies. On this vendor a stray production call is a
     * charge against a live merchant account, so the production URL is made
     * unreachable from the testing environment by construction.
     */
    private function url(): string
    {
        if (! $this->isProduction()) {
            return self::SANDBOX;
        }

        if (app()->runningUnitTests()) {
            throw new RuntimeException(
                'The Authorize.Net production endpoint is unreachable from the test '
                .'environment by design: a request there is a charge against a live '
                .'merchant account. Set services.authorizenet.environment to sandbox.'
            );
        }

        return self::PRODUCTION;
    }

    /**
     * ⚠️ **SANDBOX UNLESS PRODUCTION IS SPELLED OUT.** The default is the safe
     * one, so a deployment that forgets the variable charges nobody rather than
     * charging everybody.
     */
    private function isProduction(): bool
    {
        return config('services.authorizenet.environment') === 'production';
    }
}
