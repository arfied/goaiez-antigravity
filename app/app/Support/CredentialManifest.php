<?php

declare(strict_types=1);

namespace App\Support;

/**
 * THE CREDENTIAL MANIFEST — doc `38` Part 1's vendor list, narrowed to what this
 * codebase actually reads.
 *
 * `38` Part 1 names fourteen vendors: "Stripe (secret, webhook, Connect client)
 * · Infobip · Google (OAuth client, Places, GSC service account) · Microsoft ·
 * Meta · OpenAI/router providers · vLLM endpoint token · Rank+Keyword vendor ·
 * Cloudflare (Workers/R2/for-SaaS) · SES/SMTP · Print vendor · QBO/Xero apps ·
 * pager webhook · status-page token."
 *
 * ⚠️ **MOST OF THEM ARE NOT DECLARED HERE, AND THE RULE IS CFG1's RULE 1**
 * (decision 514): a key goes in only when this codebase reads it. Stripe is
 * row 22, Meta and the print vendor and QBO have no code at all, and `45`'s SES
 * transport is explicitly not adopted (decision 201 — Microsoft 365, then Azure
 * Communication Services). Declaring them anyway would fill the Ops board with
 * rows an operator cannot act on and cannot test, and would make "which key is
 * absent" — the one question `38` says this board must answer — return fourteen
 * answers of which two matter.
 *
 * The board grows the way the registry does: whoever builds the Infobip client
 * or wires Cashier adds their key here in the same slice.
 *
 * ## This file and `config/credentials.php` must agree, and a lint holds them to
 * it
 *
 * `38` D-149 makes env vars "bootstrap seeds only", so every key in that config
 * file is a credential this manager is responsible for. A key added to config
 * and not declared here would read from `.env` forever and never appear on the
 * board — invisible, because the code would work perfectly until the day
 * somebody rotated the key in Ops and nothing changed. The lint compares the two
 * in both directions.
 */
final class CredentialManifest
{
    /**
     * Every vendor credential this application reads.
     *
     * `vendor` groups the board. `degradation` is `38` Part 1's requirement
     * spelled out per key — "missing credential = the dependent module degrades
     * to its specced failure behavior and the health board shows exactly which
     * key is absent — never a stack trace, never a silent stall" — and it is
     * written here rather than inferred, because the honest answer differs per
     * key and only the code that reads the key knows it.
     *
     * @return array<string, array{vendor: string, label: string, description: string, degradation: string}>
     */
    public static function credentials(): array
    {
        return [
            'google_places_key' => [
                'vendor' => 'Google',
                'label' => 'Places API (New) key',
                'description' => 'Powers the free instant audit and the autocomplete on the marketing home. Runs before any tenant exists, so it cannot come from a tenant OAuth connection. Restrict it to the Places API and to server IPs.',
                // ⛔ THIS COMMENT AND THE SENTENCE UNDER IT WERE BOTH FALSE
                // UNTIL 2026-08-24, AND THE SENTENCE IS THE ONE THAT MATTERS
                // (9144). It read "PlacesClient asks has() rather than get() for
                // exactly this reason (decision 195's neighbourhood)" — and
                // `PlatformCredentials::has('google_places_key')` had no call
                // site anywhere. `GooglePlacesClient` read the key with `get()`,
                // which raises, and no caller on any of the three
                // customer-facing paths caught it. The key was unset in the
                // production registry, so the true answer was: **a 500 on the
                // marketing home, on the public audit and on wizard step 2.**
                //
                // ⛔ AND THE `degradation` STRING IS NOT A COMMENT. The Ops board
                // renders it under the words "While this is unset:" — that is,
                // to an operator, in exactly the state in which it was wrong. A
                // stale claim stored where somebody acts on it is worse than one
                // in a docblock (8860–8877).
                //
                // ⚠️ IT IS NOW WRITTEN AS A PROPERTY OF EVERY PATH RATHER THAN
                // AS AN INVENTORY OF TWO (8861). "The marketing home still
                // renders" was true and was also the whole of what the old
                // sentence promised; the wizard's business search and the queued
                // audit were never mentioned, and they are two of the three
                // places this key is spent.
                'degradation' => 'Nothing that needs Google Places can answer, and every surface that needs it says so rather than failing: the audit and the business search report that the check could not run, the autocomplete stops suggesting, and no visitor or owner meets an error page. Nothing is fabricated and no finding is invented from a missing answer.',
            ],

            'turnstile_site_key' => [
                'vendor' => 'Cloudflare',
                'label' => 'Turnstile site key',
                'description' => 'The public half, rendered into the page. Must be paired with the secret below — a dummy site key with a production secret fails verification, which is a confusing way to lose an afternoon.',
                'degradation' => 'The audit form loses its challenge widget and the 3/hr/IP rate limit becomes the only abuse control.',
            ],

            'turnstile_secret' => [
                'vendor' => 'Cloudflare',
                'label' => 'Turnstile secret',
                'description' => 'The half that makes siteverify mean anything. Never rendered anywhere.',
                'degradation' => 'Verification cannot be performed, so the audit endpoint falls back to its rate limiter alone (`29` §6.2 pairs the two).',
            ],

            'anthropic_api_key' => [
                'vendor' => 'AI providers',
                'label' => 'Anthropic API key',
                'description' => 'One of two providers behind AiRouter. Which model serves which task is a `platform_settings` row, so either provider can be the one in use at any time.',
                'degradation' => 'Tasks routed to Anthropic fail as a recorded AI call and the queued job that made them moves on. A review waiting on moderation is held for a human rather than published (decision 353).',
            ],

            'openai_api_key' => [
                'vendor' => 'AI providers',
                'label' => 'OpenAI API key',
                'description' => 'The second provider behind AiRouter. Multi-provider from day one is a deliberate override of the archived `20` §5 (CLAUDE.md).',
                'degradation' => 'Tasks routed to OpenAI fail as a recorded AI call and the queued job moves on. Same held-for-a-human outcome on the moderation path.',
            ],

            'fetch_proxy_url' => [
                'vendor' => 'Outbound proxy',
                'label' => 'Tenant-site fetch proxy URL',
                'description' => 'A full proxy URL (scheme, host, port, credentials if any) that fetches of a tenant\'s OWN website go through, for origins that block this server\'s address. Applies to the `tenant_site` fetch source only; every other source and the robots.txt read stay direct. Optional.',
                'degradation' => 'Tenant-site fetches go direct from this server. An origin that blocks this server\'s address (a Cloudflare security rule, for one) refuses them, and Site inventory records the page as "the request failed".',
            ],

            'infobip_api_key' => [
                'vendor' => 'Infobip',
                'label' => 'Infobip API key',
                // ⚠️ THE VENDOR'S SCOPE IS NAMED WITHOUT LISTING EVERY CHANNEL,
                // and that is the WhatsApp lint doing its job rather than a
                // hedge: this string renders on a screen, and the lint's rule is
                // that handling a channel is permitted while *offering* it is
                // not. A key description that enumerates a channel with no
                // specification is how the next person building row 4's sender
                // concludes it is available.
                'description' => 'Numbers, SMS and 10DLC, voice, and brand registration — not email (Microsoft 365, then Azure Communication Services). The account-specific host is configuration rather than a secret and lives in config/services.php.',
                // ✅ The client landed in row 4 slice 1 and asks
                // PlatformCredentials, which is what this entry existed to make
                // happen. The degradation below is now a real one rather than a
                // note that there is nothing to degrade.
                'degradation' => 'Text messages are refused by the transport rather than silently dropped, and the job that was sending one records the failure and moves on. Nothing is degraded today in practice: the SMS driver seeds to the log driver, so no message reaches Infobip until an operator names the carrier driver.',
            ],

            /*
             * ⛔ THE MOST CONSEQUENTIAL ABSENCE ON THIS BOARD, AND ITS
             * DEGRADATION IS WRITTEN TO SAY SO. Every other missing key here
             * costs a feature. This one costs inbound STOP handling — and a
             * STOP that never arrives is not a broken feature, it is a
             * customer's instruction honoured nowhere while every later send
             * reports as perfectly permitted.
             *
             * The failure is invisible from every screen. Infobip retries,
             * gives up, and nothing in this application ever knew there was a
             * message. That is why the string below names the symptom rather
             * than the mechanism.
             */
            'infobip_webhook_secret' => [
                'vendor' => 'Infobip',
                'label' => 'Infobip inbound webhook signing key',
                'description' => 'The HMAC-SHA256 key Infobip signs inbound webhooks with — the opposite direction to the API key above, which authenticates us to them. It is created when HMAC is configured on a notification profile in the Infobip console; HMAC is opt-in there, alongside Basic auth, OAuth 2.0 and mTLS. The signing header name is account-specific and lives in config/services.php rather than here.',
                'degradation' => '⛔ Inbound STOP, HELP and START are refused with a 401 — including genuine ones, deliberately, because an endpoint that skipped verification when unconfigured could be used by anybody to suppress any phone number. The visible symptom is nothing at all: customers who reply STOP are not recorded and not suppressed, and every later send to them looks permitted. Sending must stay off until this key is set.',
            ],

            /*
             * ⚠️ ADDED WHEN THIS BRANCH MET `main`, NOT WHEN IT WAS WRITTEN —
             * and the lint below is what found it. The Zernio client merged
             * while CFG1 was in flight, so `config/credentials.php` gained
             * `zernio_api_key` and this file did not know. `ZernioGbpClient`
             * already asks `PlatformCredentials::get()`, which is the right
             * seam, so nothing was reading config directly; what was missing was
             * the declaration that lets the store answer at all.
             *
             * That is exactly the drift the manifest/config lint exists to
             * catch, caught on its first real opportunity: two branches, neither
             * wrong on its own, and the gap only existing in the merge. Without
             * the lint the key would have read from `.env` forever and never
             * appeared on the board — working perfectly until the day somebody
             * rotated it in Ops and nothing changed.
             */
            'zernio_api_key' => [
                'vendor' => 'Zernio',
                'label' => 'Zernio API key',
                'description' => 'Our account key for the Zernio GBP proxy, which reads Google reviews on a tenant\'s behalf while our own GBP API application is pending. One key for the platform, not per tenant — the tenant\'s own authorisation lives on their Zernio connection, not here.',
                // The distinction this key's failure mode turns on is decision
                // territory the client already draws: our key failing must never
                // be reported as the tenant having disconnected their listing,
                // because the remedy is ours and the message would send them to
                // reconnect something that is not broken.
                //
                // ⚠️ THE SENTENCE BELOW WAS TRUE OF THE SYNC AND FALSE OF THE
                // SCREEN UNTIL 2026-08-24 (9145). `ZernioGbpClient` read this
                // key with `PlatformCredentials::get()`, which raises a bare
                // `RuntimeException`, and `Account\Connections` catches
                // `GbpRequestFailed`, `ImpersonationRefused` and
                // `GbpConnectionRefused` — none of which that is. So an unset
                // key was an uncaught throw out of the owner's own Connections
                // screen, while this row told an operator it "reports a
                // platform-side failure". The client now refuses with
                // `GbpRequestFailed::unconfigured()`, which every caller already
                // catches.
                'degradation' => 'Nothing that reads Google Business can answer, and every surface says so rather than failing: review sync stops and records a platform-side failure, and the owner\'s Connections screen refuses with a message instead of an error page. It is never surfaced as the tenant disconnecting their Google account — the remedy is ours — and no first-party review path is affected.',
            ],

            'zernio_webhook_secret' => [
                'vendor' => 'Zernio',
                'label' => 'Zernio webhook signing secret',
                'description' => 'HMAC-SHA256 key for inbound review and account webhooks. Header X-Zernio-Signature over the raw body. Optional on Zernio\'s side; we require it always.',
                'degradation' => '⛔ Every Zernio webhook is refused with a 401 — including genuine ones, deliberately. Near-real-time review ingest stops; the fifteen-minute gbp:sync poll still runs. Account disconnects surface only on the next health check or sync failure.',
            ],

            /*
             * ⛔ DECLARED BEFORE ANYTHING CAN USE IT, WHICH IS THE OPPOSITE OF
             * THE TWO DRIFT NOTES ABOVE AND BELOW — and the reason is that the
             * gate in front of it has to be testable. `IndexingApi` runs
             * Google's own content restriction first and this credential check
             * second; without a key an operator can plant, the credential arm
             * would refuse every call and the restriction would be
             * unfalsifiable (398).
             *
             * ⚠️ IT IS A ROW ON THE OPS BOARD WITH NO REQUEST BEHIND IT YET, AND
             * THAT IS SAID IN THE `degradation` LINE RATHER THAN LEFT FOR
             * SOMEBODY TO DISCOVER BY SETTING IT.
             */
            'google_indexing_service_account' => [
                'vendor' => 'Google',
                'label' => 'Indexing API service account',
                'description' => 'The service account JSON for Google\'s Indexing API — job postings and livestream videos only, by Google\'s own restriction. The account must also be added as a site owner of the property in Search Console, and Google must grant approval and quota; neither is a value that can be set here.',
                'degradation' => '⛔ Nothing, today: the request-making half is not built and the eligibility gate refuses every ordinary page before this key is read. Setting it changes no behaviour until a slice builds the transport, and until some table in this schema can hold a job opening.',
            ],

            /*
             * ⛔ THE SECOND INSTANCE OF THE DRIFT THE ZERNIO ENTRY ABOVE
             * DESCRIBES, AND A WORSE ONE, BECAUSE IT SAT ON THE PRIMARY SEND
             * PATH. `GmailApiClient` asks `PlatformCredentials` for all three of
             * these — the right seam — and none of them was declared here, so
             * `accessToken()` threw *"not declared in CredentialManifest"*
             * before it ever reached Google. Every send on the transport 2093
             * made primary would have failed with a message about a manifest.
             *
             * ⚠️ WHY NOBODY SAW IT: 2441 records that the Gmail transport is
             * covered only by `Http::fake()` expectations, and none of them
             * drove the token refresh — the one line where the credential is
             * read. It surfaced when the inbound half needed the same token,
             * which is 398's shape from underneath: an outer fake answered
             * first, so the inner call was never made.
             */
            'gmail_client_id' => [
                'vendor' => 'Google Workspace',
                'label' => 'Gmail internal app client ID',
                'description' => 'Names the internal OAuth app on the goaiez Workspace domain. An internal app needs no Google verification review, which is the entire reason this transport beat SES for the soft launch (2093). ⚠️ It identifies the app and authorises nothing on its own.',
                'degradation' => '⛔ No platform email is sent at all — including a sign-in link. The transport raises before it reaches Google and the job lands in failed_jobs naming this key. Nothing is silently dropped, which is the one good property of this failure.',
            ],

            'gmail_client_secret' => [
                'vendor' => 'Google Workspace',
                'label' => 'Gmail internal app client secret',
                'description' => 'Paired with the client ID at the token endpoint. ⛔ NEVER logged: a token-endpoint error body echoes the request, which is why GmailApiClient refuses to put any response body in its exception on that call.',
                'degradation' => '⛔ The same as the client ID — no platform email leaves at all.',
            ],

            /*
             * ⚠️ ONE TOKEN, TWO SCOPES, AND THE SECOND ONE IS WHY THIS
             * DESCRIPTION IS LONGER THAN ITS SIBLINGS. Whoever authorises the
             * internal app decides what the refresh token can do for ever after;
             * granting only `gmail.send` produces a working sender and an
             * inbound path that 403s on every reply, with the tenant-visible
             * symptom being that nobody ever answers their customers.
             */
            'gmail_refresh_token' => [
                'vendor' => 'Google Workspace',
                'label' => 'Gmail internal app refresh token',
                'description' => 'The platform\'s own long-lived grant on its own Workspace mailbox — never a tenant\'s (2072). ⚠️ It must be issued for BOTH scopes: `gmail.send` for the send path, and `gmail.metadata` for the inbound reply path, which is the narrowest scope authorising users.watch, users.history.list and users.messages.get (verified against Google\'s reference, 2026-08-12). `gmail.metadata` cannot read a message body, which is deliberate.',
                'degradation' => '⛔ No platform email leaves, and no inbound reply is threaded to a contact. ⚠️ A token granted for `gmail.send` alone is the failure worth naming separately: mail goes out normally and every customer reply is refused by Google with a 403, so the reply path is dead while the product looks healthy.',
            ],

            /*
             * ⛔ A SECOND WORKSPACE ACCOUNT, NOT A SECOND SCOPE ON THE ONE
             * ABOVE, AND PASTING THE SAME TOKEN INTO BOTH DEFEATS THE POINT
             * (T176 P24). The row above rests a privacy argument on the relay
             * account holding `gmail.metadata`, under which a message body
             * *cannot* be fetched rather than merely being unstored. Support
             * mail needs the body — a support ticket with no body is not a
             * support ticket — so the support account is authorised separately
             * with `gmail.readonly` and keeps its own token. One token in both
             * fields would silently widen the relay's grant, and no code here
             * can see it.
             */
            'gmail_support_refresh_token' => [
                'vendor' => 'Google Workspace',
                'label' => 'Gmail support mailbox refresh token',
                'description' => 'The platform\'s grant on its own **support** mailbox, issued for `gmail.readonly` — the narrowest scope that can read a message body, which the support desk needs and the relay account deliberately cannot do. ⛔ A DIFFERENT WORKSPACE ACCOUNT from `gmail_refresh_token`: reusing that account\'s token here would widen the relay\'s grant and quietly falsify the guarantee written on it.',
                'degradation' => '⛔ Nothing arriving at the support mailbox reaches the support desk, and nobody is told. ⚠️ The tenant-visible symptom is an email to support that is never answered, so the poller fails the job rather than returning quietly, and the failure names this key.',
            ],

            /*
             * ⚠️ THE PARAGRAPH AT THE TOP OF THIS FILE SAID "Stripe is row 22".
             * Row 22 slice B is this one, so the two keys land here in the slice
             * that reads them — which is the growth rule that paragraph states,
             * observed rather than only written down.
             */
            'stripe_secret' => [
                'vendor' => 'Stripe',
                'label' => 'Stripe secret key',
                'description' => 'Signs every outbound billing call — the customer create and the Checkout Session. Per mode: a sandbox key starts `sk_test_`. Card data never reaches this application, so this key is the whole of our access.',
                //
                // ⛔ THIS SENTENCE WAS FALSE FROM THE DAY IT WAS WRITTEN UNTIL
                // 9297, AND IT IS THE ONE THE OPS BOARD RENDERS UNDER THE WORDS
                // "While this is unset:" — that is, to an operator, in exactly
                // the state in which it was wrong (8860–8877). It read "the
                // billing route reports that payment is unavailable and offers a
                // retry, rather than a stack trace". The route reported
                // **nothing** — `Billing\BillingController`'s `RuntimeException`
                // arm redirected with no message — it offered no retry, and
                // `Account\Credit` gave a stack trace: a 500 rendered by
                // Livewire as a full-page modal.
                //
                // ⚠️ THE 9144 WAVE CORRECTED THE TWO ACCEPT.JS ENTRIES BELOW AND
                // LEFT THIS ONE AND THE TRANSACTION KEY'S BESIDE THEM. **The
                // true-sibling tell**: the corrected entries are correct, at
                // length, and in bold, which is what made these read as
                // considered.
                //
                // ⚠️ IT IS NOW A PROPERTY RATHER THAN AN INVENTORY (8861), so it
                // does not go stale the next time a surface is added.
                'degradation' => 'Nothing that spends money on Stripe can run, and every surface that offers it withholds the control rather than failing: the billing page does not offer a card, the credit screen does not offer a pack, and nobody meets an error page. Nothing is charged and no purchase is left half-open. Existing subscriptions are unaffected — they are Stripe\'s to run, and inbound webhooks still verify on their own secret.',
            ],

            'stripe_webhook_secret' => [
                'vendor' => 'Stripe',
                'label' => 'Stripe webhook signing secret',
                // ⚠️ SPELLED OUT BECAUSE THE SYMPTOM POINTS AT THE WRONG HALF.
                // This is the degradation an operator will meet first and read
                // as a Checkout defect.
                'description' => 'Verifies that an inbound event really came from Stripe. Issued per endpoint and per mode — the same URL has a different secret in test and live, and rolling it leaves both valid for up to 24 hours.',
                'degradation' => '⚠️ Every event is refused, so Stripe retries for three days and stops. Checkout still completes and the customer is charged, while their subscription stays at `pending_checkout` here — a failure that looks like a Checkout bug and is not one. Failing closed is deliberate: an endpoint accepting unverified events is one anybody can post a paid subscription to.',
            ],

            /*
             * ⚠️ FOUR KEYS, AND ONE OF THEM IS DELIBERATELY PUBLIC — which is
             * the first thing this vendor does differently from the one above.
             * Stripe's hosted Checkout puts no key in our HTML because the
             * browser leaves for Stripe's domain. Accept.js keeps the card
             * fields on our page, so the API login id and the public client key
             * are both rendered into it, and the transaction key must never be.
             */
            'authorize_net_api_login_id' => [
                'vendor' => 'Authorize.Net',
                'label' => 'Authorize.Net API login ID',
                'description' => 'Names the merchant account on every outbound call, and is also rendered into the checkout page for Accept.js. Up to 25 characters. ⚠️ It identifies the account; it authorises nothing on its own, which is why it is safe in markup beside the public client key.',
                //
                // ⛔ THE 9144 SENTENCE WAS WRONG IN TWO PLACES AND THE SECOND
                // WAS THE EXPENSIVE ONE (9297). "the server call fails with an
                // authentication error" — it does not reach the server:
                // `PlatformCredentials::get()` raised **before any HTTP call**,
                // and until 9294 that raise was not even an
                // `AuthorizeNetRequestFailed`. "every surface says so rather
                // than failing" — `Account\Credit` and
                // `Account\CancelSubscriptionController` are surfaces, said
                // nothing, and answered 500.
                //
                // ⚠️ THE COMMENT ABOVE IT ASKED FOR A COUPLING IT COULD NOT
                // ENFORCE: *"it must move in the same commit as any of those
                // three"*. A note is read by whoever already opened the file,
                // which is never the person changing the other three — the
                // paragraph named its own next victim and was not a mechanism.
                // What holds it now is `Architecture\CredentialsTest`'s census,
                // which reddens when a reader appears, moves or stops guarding.
                //
                // ⚠️ AND IT IS A PROPERTY RATHER THAN AN INVENTORY OF THREE
                // (8861): the count was already wrong, because the reader that
                // mattered — the transaction key's, one row down — was on no
                // list at all.
                'degradation' => 'Nothing that spends money on this gateway can run, and every surface that offers it withholds the control rather than failing: no card form is drawn, no card can be saved, no credit pack is offered, and nobody meets an error page. Nothing leaves this application, so nothing is charged and no card is tokenised. Existing subscriptions keep running — they are Authorize.Net\'s to charge — and inbound webhooks still verify on their own key.',
            ],

            'authorize_net_transaction_key' => [
                'vendor' => 'Authorize.Net',
                'label' => 'Authorize.Net transaction key',
                'description' => 'Signs every outbound call — the customer profile, the ARB subscription, the cancel. Up to 16 characters. ⛔ NEVER rendered into a page: it is the credential that can move money, and it sits one row away from the public client key in the merchant interface.',
                //
                // ⛔ THIS ENTRY DESCRIBED A VENDOR ERROR AND THIS KEY NEVER
                // REACHED THE VENDOR (9297). `E00007` is what a **wrong** key
                // produces; an **unset** one raised locally and nothing left the
                // process — so the symptom an operator was told to expect was
                // one they could never see.
                //
                // ⛔ AND IT WAS THE KEY WITH NO GUARD IN FRONT OF IT ANYWHERE
                // (9295). Both door guards read the two Accept.js keys and this
                // one, which is what `AuthorizeNetApi::send()` actually signs
                // with, was checked by nothing. With the other two pasted and
                // this one missed — one paste apart in Ops, and the row below
                // says these two are "routinely swapped" — the card form
                // rendered, Accept.js tokenised a **real card**, the Automatic
                // Renewal Law acknowledgement was written, and the person was
                // bounced back with no message.
                'degradation' => 'Nothing that spends money on this gateway can run, and no request is made: the refusal happens here rather than at Authorize.Net, so there is no vendor error code to look for. Every surface that offers a payment withholds the control instead — the same behaviour as the API login id above, because a door on this gateway asks one derived question rather than naming keys. The merchant interface still works, so it presents as our fault, which it is.',
            ],

            'authorize_net_signature_key' => [
                'vendor' => 'Authorize.Net',
                'label' => 'Authorize.Net webhook signature key',
                // ⚠️ SPELLED OUT BECAUSE THE SYMPTOM POINTS AT THE WRONG HALF,
                // exactly as the Stripe webhook secret's entry does — and it is
                // worse here, because this vendor publishes no retry schedule,
                // so a refused notification is not guaranteed to come back at
                // all.
                'description' => 'Verifies that an inbound notification really came from Authorize.Net: HMAC-SHA512 over the raw body, delivered in the `X-ANET-Signature` header as `sha512=<hex>`. ⚠️ Not the transaction key — the two live next to each other in the merchant interface and are routinely swapped.',
                'degradation' => '⛔ Every webhook is refused with a 401, including genuine ones, deliberately. Unlike Stripe there is no documented three-day retry window, so a refused notification may simply be lost: a subscription that goes past due, or a payment that succeeds, can leave this application\'s row stale indefinitely. The reconciliation read (`ARBGetSubscriptionStatusRequest`) is what eventually notices, not the webhook.',
            ],

            'authorize_net_public_client_key' => [
                'vendor' => 'Authorize.Net',
                'label' => 'Authorize.Net public client key',
                'description' => 'Rendered into the checkout page so Accept.js can tokenise a card in the browser. ⚠️ Public by the vendor\'s own documentation — "you cannot use the Public Client Key to initiate a transaction, you may safely store the Public Client Key in a website" — and it is what keeps SAQ-A: the card goes from the browser to Authorize.Net and comes back as an opaque nonce, so no card number reaches this application.',
                //
                // ⚠️ THE 9144 SENTENCE HELD AND IS EXTENDED RATHER THAN
                // CORRECTED. What 9295 changes is where the question is asked:
                // the two readers no longer name this key beside the API login
                // id, they ask `AuthorizeNetApi::checkoutIsConfigured()`, which
                // calls `isConfigured()` — so a door's key set can no longer be
                // a subset of the set the call behind it signs with.
                'degradation' => 'The card form is not drawn at all, so nothing is tokenised and no card data leaves the browser. Nothing is charged and nothing is stored — this fails in the safe direction, and it fails the same way as the other two keys on this gateway, because one derived question covers all three.',
            ],

            'webpush_vapid_public_key' => [
                'vendor' => 'Web Push',
                'label' => 'VAPID public key',
                'description' => 'Shown to browsers to authenticate push subscriptions. Public key.',
                'degradation' => 'Push notifications to browsers are not sent.',
            ],

            'webpush_vapid_private_key' => [
                'vendor' => 'Web Push',
                'label' => 'VAPID private key',
                'description' => 'Used to sign outgoing push notifications. Private key.',
                'degradation' => 'Push notifications to browsers are not sent.',
            ],
        ];
    }

    /**
     * Whether the manager is responsible for a key.
     *
     * Used by the store to refuse an undeclared one. A credential key is typed
     * by hand at every call site, and a typo that silently resolves to `.env`
     * and then to null is decision 505's failure mode in a place where the
     * symptom is a vendor 401 blamed on the vendor.
     */
    public static function declares(string $key): bool
    {
        return array_key_exists($key, self::credentials());
    }

    /**
     * Declared credentials grouped by vendor, for the Ops board.
     *
     * @return array<string, list<array{key: string, label: string, description: string, degradation: string}>>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::credentials() as $key => $declared) {
            $grouped[$declared['vendor']][] = [
                'key' => $key,
                'label' => $declared['label'],
                'description' => $declared['description'],
                'degradation' => $declared['degradation'],
            ];
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * How the named mailer signs in, and whether this register holds what it
     * signs in with (9374).
     *
     * ## ⛔ The finding this method exists to make visible
     *
     * ⛔ **THE CREDENTIAL THAT DECIDES WHETHER ANY PLATFORM EMAIL LEAVES — A
     * SIGN-IN LINK INCLUDED — IS THE ONE CREDENTIAL NOTHING ON THIS PLATFORM CAN
     * SEE.** `config/mail.php` reads `MAIL_USERNAME` and `MAIL_PASSWORD` from
     * the environment, so `CredentialStore::resolve()` is never asked about
     * them, `assertDeclared()` would refuse either key, `Admin\Credentials`
     * cannot list them, and **wave 26's `PlatformCredentialUnusable` bell is
     * structurally incapable of firing about them in any state** — absent,
     * blank, wrong or rotated. Production met exactly that: an SES transport
     * holding an Infobip-format username, three permanent failures eighteen
     * minutes apart, and every credential on the Ops board green throughout.
     *
     * ⚠️ **THE TRUE SIBLING IS TWO HUNDRED LINES UP AND IS WHAT MADE THE GAP
     * READ AS CONSIDERED.** `gmail_refresh_token`'s degradation sentence says
     * *"the job lands in failed\_jobs naming this key. Nothing is silently
     * dropped, which is the one good property of this failure"* — correct about
     * a credential this register declares, and it does not extend one inch to
     * the SMTP pair beside it.
     *
     * ## ⚠️ Why they are not simply added to the register
     *
     * ⚠️ **BECAUSE THE FRAMEWORK READS THEM BEFORE ANY OF OUR CODE RUNS.**
     * Laravel's `MailManager` resolves a transport from `config('mail.mailers.…')`,
     * so moving the pair behind this register means overriding that
     * configuration at boot — which puts a database read and a decryption on the
     * path that issues a sign-in link, in an application whose own
     * `CredentialStore` docblock records what an `APP_KEY` rotation does to
     * every stored ciphertext. **That trade was refused** (9374). What is not
     * optional is that the gap stops being invisible, and this method is what
     * `Admin\MailSending` says it with.
     *
     * ## ⚠️ Derived from the mailer's own block, with one enumerated arm
     *
     * ⚠️ **`authenticates` IS READ OFF THE CONFIGURATION RATHER THAN LISTED**,
     * so a mailer block added tomorrow with a username in it gets the true
     * sentence without anybody remembering to come here. **`key` is the one
     * enumerated arm** and it is real: the Gmail API transport signs in with
     * `gmail_refresh_token`, which this register *does* hold, so the screen
     * points at Ops rather than at `.env` on that transport. A default of null
     * makes no claim.
     *
     * ⛔ **THREE ANSWERS RATHER THAN TWO, BECAUSE *"IT SIGNS IN WITH SOMETHING
     * WE CANNOT SEE"* AND *"IT SIGNS IN WITH NOTHING"* ARE DIFFERENT
     * SENTENCES.** An `smtp` block with no username is an anonymous relay — the
     * shape `config/mail.php` ships by default at `127.0.0.1:2525` — and telling
     * an operator to go and check a password that does not exist is the
     * *"a phrase that names two different outcomes"* failure with a wasted hour
     * attached.
     *
     * ⛔ **NOTHING HERE READS, RETURNS OR HINTS AT A VALUE.** `authenticates` is
     * a boolean about whether a username is configured at all, on
     * `CredentialStore::board()`'s own property: this surface has no path to a
     * secret, which is what lets it be rendered at all.
     *
     * @return array{authenticates: bool, key: string|null}
     */
    public static function mailerCredential(string $mailer): array
    {
        $username = config("mail.mailers.{$mailer}.username");
        $transport = config("mail.mailers.{$mailer}.transport");

        $key = ($transport === 'gmail') ? 'gmail_refresh_token' : null;

        return [
            'authenticates' => is_string($username) && trim($username) !== '',
            'key' => $key !== null && self::declares($key) ? $key : null,
        ];
    }
}
