{{--
    The card form — Accept.js, and the reason no card field on this page is ever
    submitted to us.

    ⛔ **NO INPUT BELOW THAT CARRIES A CARD CREDENTIAL HAS A `name`, AND THAT IS
    THE WHOLE OF SAQ-A ON THIS PAGE.** A field with no `name` is not serialised
    by the browser, so the card number, expiry and CVV never enter the POST body
    at all — Accept.js reads them from the DOM by `id`, sends them straight to
    Authorize.Net, and hands back an opaque nonce. **Adding a `name` attribute to
    any of them puts a card number in our request logs**, and nothing in the PHP
    would look different. `AuthorizeNetCheckoutRequest` refuses any field outside
    its allowlist for exactly that reason, and it is the second layer rather than
    the first.

    ⚠️ **THIS SENTENCE READ *"EVERY CARD INPUT BELOW HAS `data-` ATTRIBUTES AND
    NO `name`"* UNTIL 2026-08-29 AND BOTH HALVES HAD DRIFTED.** The card inputs
    carry an `id` and no `data-` attribute — Accept.js is handed their values by
    this page's own script rather than finding them — and since this page grew
    the two cardholder-name fields, *"every card input"* is no longer the same
    set as *"every input a browser fills from the card record"*. The property
    that is actually load-bearing is the one now stated: a **credential** — the
    PAN, the expiry, the security code — is never serialised. The two name
    fields are, deliberately, and the form request's Luhn tripwire reads every
    submitted value because of it.

    ⚠️ **THE FORM IS `onsubmit`-INTERCEPTED AND SUBMITS ONLY WHAT THE ALLOWLIST
    NAMES.** ⛔ **The neighbouring sentence said *"the two hidden inputs are the
    only fields with names besides the CSRF token"* and had been false since
    2026-08-12** — `term`, `instalments`, `locations`, `quoted_total_cents` and
    `auto_renewal_ack` all carry names, and none is a card field. A count of
    named inputs is not the property; `AuthorizeNetCheckoutRequest::PERMITTED` is
    the list, it fails the request rather than a reader's memory, and it is the
    one place to look.

    ⚠️ **THE SCRIPT HOST DIFFERS BETWEEN SANDBOX AND PRODUCTION** on this vendor —
    `jstest.authorize.net` against `js.authorize.net` — where Stripe distinguishes
    modes by the key alone. The controller picks it; loading the wrong one fails
    in the browser with a message about authentication, which reads as a bad key.

    Uses the setup shell, because for almost everybody this page is part of
    signup — the same reasoning as `billing/index` (decision 690).
--}}

<x-setup.layout title="Add a card">
    <h1 class="font-display text-3xl font-semibold tracking-tight text-ink">Add a card</h1>

    {{--
        ⛔ **THE ONE MESSAGE THIS PAGE CAN BE SENT BACK WITH** (4640): the price
        moved while the tab was open, so nothing was charged and the figures
        below are today's. Without this block the redirect would land somebody on
        a page whose amount had silently changed with nothing to say why — which
        is the confusing half of the defect surviving its own fix.

        `role="alert"` and `aria-live` because it appears after the page has been
        read once; `text-alert` is paired with words rather than carrying the
        meaning alone (`22`: colour is information, never the sole indicator).
    --}}
    @if (session()->has('billing.error'))
        <p class="mt-4 rounded-lg border border-rule-strong p-4 text-base text-alert" role="alert" aria-live="polite">
            {{ session('billing.error') }}
        </p>
    @endif

    <p class="mt-4 text-base text-ink-2">
        Your {{ $trialDays }}-day free trial runs first. After that it is
        {{ $price }} {{ $term === \App\Enums\BillingTerm::Annual ? 'a year' : 'a month' }},
        and you can cancel any time.
    </p>

    {{--
        ⛔ **THE BREAKDOWN IS PART OF THE CONFIRMATION, ON THE SCHEDULE BLOCK'S
        OWN REASONING.** `$price` above is the *total* — base plus every extra
        location — so a buyer with three locations reads one number that is three
        times what any page ever quoted them per location. `CLAUDE.md` reserves
        CONFIRM for anything that spends money, and a press is only a
        confirmation of what the page named: so when locations are on the order,
        the two rates and the count are named before the button.

        Nothing here is a literal (512). Both figures come from `PlanCharges` on
        the same selection the charge is built from.
    --}}
    @if ($additionalLocations > 0)
        <div class="mt-4 rounded-lg border border-rule-strong p-4">
            <p class="text-base font-medium text-ink">
                You are buying {{ $additionalLocations + 1 }} locations:
            </p>

            <ul class="mt-2 space-y-1 text-base text-ink-2">
                <li>{{ $basePrice }} — your plan, including your first location</li>
                <li>
                    {{ $addOnPrice }} × {{ $additionalLocations }} —
                    {{ $additionalLocations === 1 ? 'one more location' : 'each location after the first' }}
                </li>
            </ul>

            <p class="mt-2 text-sm text-ink-2">
                That comes to {{ $price }}
                {{ $term === \App\Enums\BillingTerm::Annual ? 'a year' : 'a month' }}.
            </p>
        </div>
    @endif

    {{--
        ⚠️ **THE SCHEDULE IS THE CONFIRMATION, NOT A DECORATION** (decision 2680).
        `CLAUDE.md` reserves CONFIRM for "anything that spends money", and on an
        instalment plan money moves three times from one press of Save card. The
        press is only a confirmation of *that* if the page names every amount and
        every date first — so this block is what makes the button honest, and
        removing it would leave two of the three charges unannounced.

        Dates and amounts are derived (2055): nothing here is a literal, and the
        figures move with the registry price like every other quoted number.

        ⛔ **AND THAT INCLUDES THE COUNT AND THE REMAINDER SENTENCE, WHICH IT DID
        NOT UNTIL 4643.** This block read *"the last payment is a cent larger so
        the **three** add up to the yearly price exactly"* directly beneath a
        `@foreach` over however many payments the schedule holds. **The founder
        annual is TWO payments and the retail annual is THREE, and neither is a
        bug** (2092, 2754, 4315) — a test fails the build if somebody harmonises
        them, because the tidy-minded edit silently reprices a year. So the
        founder buyer read a page telling them, in the confirmation block that
        makes the button honest, that they were making a payment the page did not
        list.

        ⚠️ **"A CENT" WAS THE SECOND WRONG HALF.** The split is per SKU and then
        summed (`PlanCharges`), so a three-location instalment plan carries one
        cent of remainder per SKU — and the founder annual add-on
        (`PlanOfferCatalog`) divides evenly across its two payments, so on that
        purchase **no** payment is larger at all. The sentence is therefore
        conditional and says nothing about how much.

        ⚠️ **THE FIGURE IS NAMED BY ITS KEY AND NOT WRITTEN OUT**, here of all
        places: `RegistryTest` fails the build on a plan-price literal anywhere
        outside the two files that seed one, and **a template is scanned with its
        comments intact** — reasonably, since this is a file that prints prices.
        This comment carried the cents once and reddened that lint (4643).
    --}}
    @if ($instalments !== [])
        <div class="mt-4 rounded-lg border border-rule-strong p-4">
            <p class="text-base font-medium text-ink">
                You are paying in {{ count($instalments) }} instalments:
            </p>

            <ul class="mt-2 space-y-1 text-base text-ink-2">
                @foreach ($instalments as $payment)
                    <li>{{ $payment['amount'] }} on {{ $payment['on'] }}</li>
                @endforeach
            </ul>

            <p class="mt-2 text-sm text-ink-2">
                That covers a full year.
                @if ($finalInstalmentCarriesRemainder)
                    The last payment is slightly larger, so the payments come to the
                    yearly price exactly.
                @endif
            </p>
        </div>
    @endif

    {{--
        The accepted methods, per T137 R2: credit and debit — Visa, Mastercard,
        Amex, Discover.

        ⚠️ TEXT, NOT IMAGES, AND NOT COLOUR ALONE. `22`'s rule is that colour is
        information and never the sole indicator; four brand marks with no text
        are exactly that, and they also fail at 320px and in a screen reader.
        Naming them is what a person actually needs — "is my card accepted" — and
        it needs no asset pipeline, no third-party image host and no logo licence.
    --}}
    <p class="mt-2 text-sm text-ink-2">
        Credit and debit cards accepted: Visa, Mastercard, American Express, Discover.
    </p>

    <form id="payment-form" method="POST" action="{{ route('billing.card.store') }}" class="mt-8 max-w-md">
        @csrf

        {{-- The nonce fields. Filled by Accept.js, never by a person. --}}
        <input type="hidden" name="data_descriptor" id="data-descriptor">
        <input type="hidden" name="data_value" id="data-value">

        {{--
            ⚠️ **THE TERM IS POSTED BACK RATHER THAN RE-READ FROM THE QUERY
            STRING**, so that what is charged is what this page quoted. A POST
            that resolved the term from anywhere else could bill a year against a
            page that said "a month" — and the two are three hundred pounds apart.
            Both fields are on `AuthorizeNetCheckoutRequest`'s allowlist by
            deliberate act (2145, widened at 2680); everything else is still
            refused.
        --}}
        <input type="hidden" name="term" value="{{ $term->value }}">
        <input type="hidden" name="instalments" value="{{ $buyingInstalments ? '1' : '0' }}">

        {{--
            ⚠️ POSTED BACK FOR THE TERM'S OWN REASON, WHICH IS SHARPER HERE. If
            this count were re-read from anywhere else, the charge could carry a
            quantity the breakdown above never named — and unlike the term, the
            two would still both be plausible amounts.
        --}}
        <input type="hidden" name="locations" value="{{ $additionalLocations }}">

        {{--
            ⛔ **WHAT THIS PAGE QUOTED — A CHECK, AND NEVER A PRICE** (4640).

            The three fields above post back the *choice* so that what is charged
            is what was quoted. They cannot express the other half of that
            promise: a founder window closing between this render and the button
            leaves the choice identical and the price different, so the submit
            charged the retail figure against a screen — and against the
            Automatic Renewal Law acknowledgment below — that said founder.

            ⛔ **NOTHING EVER SPENDS THIS NUMBER.** The controller and the gateway
            each derive the total themselves and compare; a mismatch refuses the
            purchase and charges nothing. So editing this field cannot buy
            anything cheaply — it can only make a legitimate purchase fail — which
            is why it needs no signature and why `PlanSelection`'s refusal to hold
            a price is not being worked around here. See `QuotedPriceChanged`.
        --}}
        <input type="hidden" name="quoted_total_cents" value="{{ $quotedTotalCents }}">

        {{--
            ⛔ **THE NAME ON THE CARD, AND WITHOUT IT NO TENANT HAS EVER BEEN ABLE
            TO SUBSCRIBE.** Authorize.Net will not create a subscription against
            a payment profile that carries no `billTo`: the request comes back
            `E00014`, *"Bill-To First Name is required."*, inside a 200 — and
            this page's own controller reported that to the buyer as *"That card
            could not be accepted."* Three live probes settled where it goes: on
            the **payment profile**, never on the subscription (`E00093`), and
            first + last alone is enough.

            ⛔ **THESE ARE THE ONLY TWO INPUTS ON THIS PAGE THAT CARRY A `name`
            AND ARE FILLED FROM THE STORED CARD RECORD, AND BOTH HALVES OF THAT
            SENTENCE MATTER.** `cc-given-name` and `cc-family-name` are the
            autofill spec's own decomposition of `cc-name` and are the same
            autofill group as `cc-number` — chosen over `cc-name` because the
            vendor wants two fields and splitting one in the browser would guess
            where a name divides, and over the plain `name` token because that
            fills from the browser's **address** profile, which is the person
            using the computer rather than the person whose card this is.

            ⚠️ **THE PRICE OF THAT CHOICE IS PAID IN THE FORM REQUEST.** A field
            in the card autofill group that is actually serialised is a field a
            mis-mapping browser or extension can put a card number into, so
            `AuthorizeNetCheckoutRequest`'s Luhn tripwire now reads **every**
            submitted value rather than the nonce alone, and `CardholderName`
            refuses a card-shaped name again at the boundary. A cardholder's
            name is not cardholder data without a PAN beside it; what would end
            SAQ-A here is the PAN, and that is what both layers look for.

            ⚠️ **THE ERRORS ARE RENDERED.** Until this page grew a field somebody
            can get wrong, no per-field validation message was ever displayed on
            it — a refusal from the form request redirected back to a page with
            nothing on it to say why, which reads exactly like a button that does
            nothing.
        --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="cardholder-first-name" class="block text-base font-medium text-ink">First name on the card</label>
                <input
                    type="text"
                    id="cardholder-first-name"
                    name="cardholder_first_name"
                    value="{{ old('cardholder_first_name') }}"
                    autocomplete="cc-given-name"
                    maxlength="{{ \App\Support\CardholderName::MAX_LENGTH }}"
                    required
                    class="mt-2 block w-full rounded-lg border border-rule-strong px-3 py-2 text-base text-ink"
                >

                @error('cardholder_first_name')
                    <p class="mt-2 text-base text-alert" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="cardholder-last-name" class="block text-base font-medium text-ink">Last name on the card</label>
                <input
                    type="text"
                    id="cardholder-last-name"
                    name="cardholder_last_name"
                    value="{{ old('cardholder_last_name') }}"
                    autocomplete="cc-family-name"
                    maxlength="{{ \App\Support\CardholderName::MAX_LENGTH }}"
                    required
                    class="mt-2 block w-full rounded-lg border border-rule-strong px-3 py-2 text-base text-ink"
                >

                @error('cardholder_last_name')
                    <p class="mt-2 text-base text-alert" role="alert">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <p class="mt-2 text-sm text-ink-2">
            Enter them exactly as they appear on the card. It does not have to be your own card.
        </p>

        <div class="mt-4">
            <label for="card-number" class="block text-base font-medium text-ink">Card number</label>
            {{-- ⛔ NO `name` ATTRIBUTE. See the comment at the top of this file. --}}
            <input
                type="text"
                id="card-number"
                inputmode="numeric"
                autocomplete="cc-number"
                required
                class="mt-2 block w-full rounded-lg border border-rule-strong px-3 py-2 text-base text-ink"
            >

            {{--
                ⚠️ **THE NONCE FIELD'S OWN REFUSALS, WHICH NOTHING RENDERED
                UNTIL NOW.** `data_value` is a hidden input, so its error had
                nowhere to appear — and the two sentences it carries are the
                ones about a form that submitted more than it should have and a
                card-shaped value reaching the token field. Shown here because
                this is the field the person would look at.
            --}}
            @error('data_value')
                <p class="mt-2 text-base text-alert" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="mt-4 grid grid-cols-3 gap-3">
            <div>
                <label for="exp-month" class="block text-base font-medium text-ink">Month</label>
                <input type="text" id="exp-month" inputmode="numeric" autocomplete="cc-exp-month" required
                       class="mt-2 block w-full rounded-lg border border-rule-strong px-3 py-2 text-base text-ink">
            </div>
            <div>
                <label for="exp-year" class="block text-base font-medium text-ink">Year</label>
                <input type="text" id="exp-year" inputmode="numeric" autocomplete="cc-exp-year" required
                       class="mt-2 block w-full rounded-lg border border-rule-strong px-3 py-2 text-base text-ink">
            </div>
            <div>
                <label for="card-code" class="block text-base font-medium text-ink">Security code</label>
                <input type="text" id="card-code" inputmode="numeric" autocomplete="cc-csc" required
                       class="mt-2 block w-full rounded-lg border border-rule-strong px-3 py-2 text-base text-ink">
            </div>
        </div>

        {{--
            ⚠️ **THE AUTO-RENEWAL ACKNOWLEDGMENT** (2980–2999). California's
            Automatic Renewal Law asks for three things — the renewal terms
            presented clearly and acknowledged **separately from the purchase**,
            a reminder before a long renewal, and a cancellation mechanism at
            least as easy as the signup. This is the first; `/account/plan` is
            the third; and until 2026-08-12 this codebase had none of them while
            the sentence at the top of this page promised the third.

            ⚠️ **THE WORDS COME FROM `AutoRenewalDisclosure` AND ARE STORED WITH
            THE ROW.** A page that renders one paragraph while the record claims
            another proves nothing, so the text on screen and the text in
            `auto_renewal_acknowledgements.proof` are the same string from the
            same method — and the version is stored beside it, so "who agreed to
            this exact wording" is answerable a year later.

            ⛔ **UNCHECKED, AND `accepted` ON THE FORM REQUEST IS WHAT ENFORCES
            IT.** An unticked box is not submitted at all, so `boolean` would let
            it through as false; the rule is `accepted`, and the request refuses
            the post outright.

            ⚠️ **AN INSTALMENT PLAN GETS DIFFERENT WORDS** (2749): three ARB
            payments complete inside the first quarter and nothing renews it, so
            the renewing sentence would be false on that purchase.
        --}}
        <div class="mt-6 rounded-lg border border-rule-strong p-4">
            <p class="text-base text-ink-2">{{ $renewalDisclosure }}</p>

            <label class="mt-4 flex items-start gap-3">
                <input
                    type="checkbox"
                    name="auto_renewal_ack"
                    value="1"
                    class="mt-1 size-5 rounded border-rule-strong text-ink"
                >
                <span class="text-base text-ink">{{ $renewalLabel }}</span>
            </label>

            @error('auto_renewal_ack')
                <p class="mt-3 text-base text-alert" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <p id="card-error" role="alert" aria-live="polite" class="mt-4 text-base text-alert empty:hidden"></p>

        <x-ui.button type="submit" id="pay-button" class="mt-6">
            Save card
        </x-ui.button>
    </form>

    {{--
        Inline rather than pushed to a stack: the setup shell has no `@stack`, and
        adding one to a layout three other lanes are editing today is a wider
        change than this page needs.
    --}}
        <script src="{{ $acceptJsUrl }}" charset="utf-8"></script>
        <script>
            // ⚠️ THE API LOGIN ID AND THE PUBLIC CLIENT KEY ARE IN THIS PAGE ON
            // PURPOSE. The vendor's own documentation: "you cannot use the Public
            // Client Key to initiate a transaction, you may safely store the
            // Public Client Key in a website". ⛔ The TRANSACTION key is a
            // different value and never appears here.
            (function () {
                const form = document.getElementById('payment-form');
                const button = document.getElementById('pay-button');
                const error = document.getElementById('card-error');

                form.addEventListener('submit', function (event) {
                    // ⛔ ALWAYS PREVENTED. If Accept.js failed to load, this
                    // stops the form submitting the card fields as an ordinary
                    // POST — which is the one failure that would put a card
                    // number in our logs, and it is the failure most likely to
                    // happen (a blocked script, an ad blocker, an outage).
                    event.preventDefault();

                    if (typeof Accept === 'undefined') {
                        error.textContent = 'We could not load the secure card form. Please refresh and try again.';
                        return;
                    }

                    button.disabled = true;
                    error.textContent = '';

                    Accept.dispatchData({
                        authData: {
                            clientKey: @json($clientKey),
                            apiLoginID: @json($apiLoginId),
                        },
                        cardData: {
                            cardNumber: document.getElementById('card-number').value.replace(/\s/g, ''),
                            month: document.getElementById('exp-month').value,
                            year: document.getElementById('exp-year').value,
                            cardCode: document.getElementById('card-code').value,
                        },
                    }, function (response) {
                        if (response.messages.resultCode !== 'Ok') {
                            button.disabled = false;
                            // The vendor's message text is shown to the person
                            // and not logged: at this point it is a validation
                            // hint about their own card, in their own browser.
                            error.textContent = 'Please check the card details and try again.';
                            return;
                        }

                        document.getElementById('data-descriptor').value = response.opaqueData.dataDescriptor;
                        document.getElementById('data-value').value = response.opaqueData.dataValue;

                        // ⛔ THE CARD FIELDS ARE CLEARED BEFORE THE POST. They
                        // have no `name` and would not be serialised anyway;
                        // this is the belt to that braces, and it costs four
                        // lines.
                        ['card-number', 'exp-month', 'exp-year', 'card-code'].forEach(function (id) {
                            document.getElementById(id).value = '';
                        });

                        form.submit();
                    });
                });
            })();
        </script>
</x-setup.layout>
