<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Platform mail — the multi-driver seam
|--------------------------------------------------------------------------
|
| `config/mail.php` says which mailer carries a message. This file says what
| each mailer can tell us afterwards, and holds the Gmail API transport's own
| settings. The split is deliberate: `config/mail.php` is excluded from the
| subprocessor scan by name (every line of it is per-deployment transport), so
| a vendor endpoint written there is invisible to `OutboundTest`. This file is
| scanned, which is why the Gmail endpoints live here and not there.
|
| ⚠️ **SES NEEDS NO ENTRY IN `config/mail.php` AND THAT IS THE SEAM WORKING.**
| `CLAUDE.md` §Vendors: *"SES **is** SMTP: the stock `smtp` transport with SES
| credentials, so 1191's one-variable change survives"*. Slotting SES in is
| `MAIL_MAILER=smtp` with SES's host, username and
| password — no code, no new mailer block, no deploy beyond `.env`. The only
| thing this file adds is the knowledge that when `smtp` is pointed at SES and
| the SNS webhook is wired, the feedback signal is typed.
|
| ⚠️ **AND THAT IS NOW THE INTENDED TRANSPORT RATHER THAN THE STANDBY (R16).**
| 2093 made Google Workspace primary at soft launch on approval speed; R16
| reverses the primacy and returns it to SES, on the ground 2094 already
| recorded — Gmail has no typed bounce or complaint webhook, so a Workspace-
| primary path leaves NDR text in a mailbox as the only signal, and the
| complaint half is not recoverable at all. Workspace is not un-adopted: it
| stays behind this same seam and carries the **inbound** reply and
| support-mailbox reads, which SES does not do.
|
| ⛔ **IT DOES NOT "STAY FOR STAFF AND SUPPORT MAIL", WHICH IS WHAT THIS BLOCK
| SAID UNTIL 2026-08-16** (4454). There is one `mail.default` and
| `PlatformMailer::deliverNow()` routes **every** outbound message through it,
| so `MAIL_MAILER=smtp` leaves Workspace carrying no outbound mail at all — not
| staff mail, not support mail, not one message. The seam is a switch, not a
| split; `MAIL_MAILER=gmail` still selects it and moves *everything* back. What
| genuinely survives the flip is `GmailInbox` and `SupportMailbox`, neither of
| which reads `MAIL_MAILER`, and that is the whole reason this driver stays
| wired.
|
| ⛔ **NOTHING HERE UNBLOCKS THE FIRST CUSTOMER-FACING SEND.** SES production
| access and a signed AWS DPA are the owner's to obtain and neither is code. A
| fresh SES account is in the sandbox — 200 messages per 24 hours, 1 message
| per second, and mail only to verified addresses (docs.aws.amazon.com/ses/
| latest/dg/request-production-access.html, read 2026-08-16).
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | What each mailer can tell us after a message leaves
    |--------------------------------------------------------------------------
    |
    | Keyed by the *mailer* name from `config/mail.php`, valued with a
    | `MailFeedbackSignal` case. Read by `MailDrivers`, which answers `None`
    | for anything unlisted — the fail-closed direction, because the value
    | decides whether this application may email somebody else's customer.
    |
    | ⚠️ **`smtp` IS `none` UNTIL SOMEBODY SAYS OTHERWISE, AND THAT IS NOT A
    | JUDGEMENT ABOUT SES.** The `smtp` mailer is whatever host `.env` points
    | it at: SES with a configured SNS topic, a relay with a webhook nobody
    | has wired, or the cPanel box 1195 forbids for customer mail. The
    | deployment that has actually connected the feedback loop says so with
    | `PLATFORM_MAIL_SMTP_FEEDBACK=typed`, and a deployment that has not gets
    | the answer that refuses. Defaulting this to `typed` because SES is the
    | adopted vendor would assert a bounce feed that may not be subscribed —
    | 314-316's shape, in a config file. ⚠️ **R16 makes SES primary and does
    | NOT change that**: the seed stays `none`, because primacy is a decision
    | about which vendor we intend and this key is a claim about which webhook
    | is actually wired.
    |
    | ⛔ **AND SAYING `typed` IS NOW NOT ENOUGH ON ITS OWN.** `MailDrivers`
    | refuses to honour it while `sns.topic_arns` below is empty, because that
    | is the combination in which customer mail is permitted and every bounce
    | and complaint about it is refused at the door with a 401.
    |
    */

    'feedback' => [
        'gmail' => env('PLATFORM_MAIL_GMAIL_FEEDBACK', 'ndr_only'),
        'smtp' => env('PLATFORM_MAIL_SMTP_FEEDBACK', 'none'),
        'ses' => env('PLATFORM_MAIL_SES_FEEDBACK', 'none'),
        'log' => 'none',
        'array' => 'none',
    ],

    /*
    |--------------------------------------------------------------------------
    | The Gmail API transport (T137 R3, decision 2093)
    |--------------------------------------------------------------------------
    |
    | An internal OAuth app on the goaiez Workspace domain, sending as one
    | Workspace user. Verified against Google's own reference on 2026-08-11:
    |
    |   POST https://gmail.googleapis.com/gmail/v1/users/{userId}/messages/send
    |   body  {"raw": "<base64url of the RFC 2822 message>"}
    |   scope https://www.googleapis.com/auth/gmail.send
    |
    | (developers.google.com/workspace/gmail/api/reference/rest/v1/
    | users.messages/send, page dated 15 April 2026.)
    |
    | ⚠️ **`gmail.send` AND NOTHING WIDER.** `gmail.modify` and
    | `https://mail.google.com/` also authorise this call and both grant read
    | access to the whole mailbox. The narrowest scope that sends is the one
    | asked for, because an internal app needs no verification review and the
    | temptation is therefore to ask for everything at once.
    |
    */

    'gmail' => [

        // The Workspace user this application sends as. `me` resolves to the
        // authorised user, which is what an internal app's single grant gives
        // us; an explicit address is here for the day a second sending account
        // exists and the meter has to tell them apart.
        'user' => env('GMAIL_SEND_AS', 'me'),

        'send_endpoint' => 'https://gmail.googleapis.com/gmail/v1/users',

        // The same token endpoint `config/oauth.php` already names for the
        // tenant-side Google refresh. Repeated rather than reached for across
        // files, because this is the *platform's own* grant out of
        // `PlatformCredentials` and that one is a tenant's out of the vault —
        // 2072's two Microsofts, in Google's clothing.
        'token_endpoint' => 'https://oauth2.googleapis.com/token',

        'scope' => 'https://www.googleapis.com/auth/gmail.send',

        'timeout' => (int) env('GMAIL_TIMEOUT', 15),

        /*
        |----------------------------------------------------------------------
        | Reading the same mailbox — the inbound half (T137 §3 rail 3)
        |----------------------------------------------------------------------
        |
        | ⚠️ **`gmail.send` DOES NOT AUTHORISE ANY OF THIS, AND THE BLOCK ABOVE
        | SAYS "AND NOTHING WIDER".** Both sentences are true and they are about
        | different calls. Verified against Google's own reference on
        | 2026-08-12: `users.watch`, `users.history.list` and `users.messages.get`
        | each list exactly four authorising scopes —
        | `https://mail.google.com/`, `gmail.modify`, `gmail.readonly` and
        | `gmail.metadata` — and `gmail.send` is on none of them. So the internal
        | app's grant needs a **second** scope, and the narrowest of the four is
        | the one asked for.
        |
        | ✅ **`gmail.metadata` IS AN EXACT FIT RATHER THAN A COMPROMISE, AND THE
        | REASON IS A PRIVACY DECISION THIS APPLICATION HAD ALREADY MADE.**
        | Google's scope page: *"View your email message metadata such as labels
        | and headers, but not the email body."* The `Format` reference is
        | explicit that `full` and `raw` *"cannot be used when accessing the API
        | with the gmail.metadata scope"*. `MailReplyRouter` deliberately does not
        | store the text of a reply — so under this scope the text is not merely
        | unstored, it is **unreadable**, and a later change of heart would have
        | to go and ask Google for a wider grant. That is the strongest form of
        | the refusal, and it costs nothing because the only thing the router
        | needs is the address the reply was sent *to*.
        |
        */

        'inbox' => [

            // The mailbox `users.watch` is registered against and history is
            // read from. `me` resolves to the authorised user, as it does for
            // the send path above.
            'mailbox' => env('GMAIL_INBOX_USER', 'me'),

            // Same host, same version prefix as `send_endpoint`; the resource
            // differs per call (`/history`, `/messages/{id}`).
            'read_endpoint' => 'https://gmail.googleapis.com/gmail/v1/users',

            'scope' => 'https://www.googleapis.com/auth/gmail.metadata',

            // ⚠️ **THE ONLY HEADERS ASKED FOR, AND THE LIST IS SHORT ON
            // PURPOSE.** `metadataHeaders[]` restricts what comes back, so
            // asking for three is asking Google to send us three. `Subject`,
            // `From` and `Message-ID` are all available and are none of our
            // business: the tracking code rides in the recipient address, and a
            // header we do not request is personal data that never crosses the
            // boundary at all.
            'recipient_headers' => ['To', 'Delivered-To', 'Cc'],

            // ⚠️ **BOTH OF THESE ARE THE RATE LIMIT WRITTEN DOWN, AND THE
            // ARITHMETIC IS THE REASON THEY ARE THIS LOW.** Gmail's usage-limits
            // page (`developers.google.com/workspace/gmail/api/reference/quota`,
            // read 2026-08-12, dated 2026-07-31) gives **6,000 quota units per
            // minute per user per project**, and prices `history.list` at 2 and
            // `messages.get` at **20**. So one notification that walked five
            // pages of a hundred would cost 10,002 units — over the ceiling on
            // its own, before a second notification arrived. A hundred messages
            // is 2,002, which leaves room for the bursts a busy relay produces.
            //
            // ⛔ **A MAILBOX WITH MORE WAITING IS NOT DROPPED**: the cursor
            // advances only as far as was actually read, so the remainder is
            // picked up by the next notification rather than skipped.
            'max_history_pages' => (int) env('GMAIL_INBOX_MAX_HISTORY_PAGES', 3),
            'max_messages_per_run' => (int) env('GMAIL_INBOX_MAX_MESSAGES', 100),

            // ⛔ **THE KILL SWITCH, AND IT SEEDS OFF.** Nothing about this path
            // may run until the internal app has been granted `gmail.metadata`
            // and `users.watch` has been called against a Pub/Sub topic — before
            // that, every notification is a forgery by definition, because
            // nothing genuine can exist. ⚠️ It is config rather than a
            // `DefaultsRegistry` seed only because `DefaultsManifest` belongs to
            // another lane this slice may not edit; the registry key is reported
            // as a seam.
            'enabled' => (bool) env('PLATFORM_MAIL_INBOUND_ENABLED', false),
        ],

        /*
        |----------------------------------------------------------------------
        | The support mailbox — a SECOND Workspace account, on a SECOND grant
        |----------------------------------------------------------------------
        |
        | T176 §3: the support desk existed with nothing feeding it. This block
        | is what feeds it, and the two things it must not be confused with are
        | in the two blocks above and below.
        |
        | ⛔ **THIS IS NOT THE RELAY MAILBOX AND IT MUST NOT BE POINTED AT IT.**
        | The `inbox` block above bookmarks its position in *one* mailbox's
        | history and `IngestGmailPushJob` advances that bookmark as it consumes.
        | Two consumers walking one bookmark is not "two cursors that disagree",
        | it is worse — each advances the other past mail it never saw, and the
        | loss is silent. `SupportMailbox::isEnabled()` therefore refuses to run
        | when this value equals `inbox.mailbox`, and a test drives that refusal.
        | `mail_inbox_cursors` is keyed `(mailer, mailbox)` precisely so two read
        | paths can coexist; that only helps when the two names differ.
        |
        | ⛔ **IT IS ALSO NOT A TENANT'S OWN MAILBOX** (2072). This is the goaiez
        | support account. Tenant mailbox OAuth is a different credential, a
        | different vault and a deferred feature.
        |
        | ⚠️ **THE SCOPE IS `gmail.readonly` AND THAT IS A DELIBERATE WIDENING
        | THAT DOES NOT REACH THE RELAY.** The `inbox` block above chooses
        | `gmail.metadata` so that reply text *cannot* be fetched rather than
        | merely being unstored, and that argument is load-bearing — it stays
        | true, because a Google refresh token carries the scopes granted to
        | **one authorised account**. Reading support mail needs the body: a
        | support ticket with no body is not a support ticket. So the support
        | account is authorised separately, its refresh token is a separate
        | credential (`gmail_support_refresh_token`), and the relay account's
        | grant is untouched. ⚠️ If somebody ever authorises **one** account for
        | both, that argument dies quietly — which is the other reason the
        | mailboxes are compared above.
        |
        */

        'support' => [

            // ⚠️ **NO `me` DEFAULT, AND THAT IS THE FAIL-CLOSED DIRECTION.**
            // `me` resolves to whichever account the refresh token belongs to,
            // which is exactly the collision the block above refuses — and it
            // would read as configured. An empty value disables the path.
            'mailbox' => env('GMAIL_SUPPORT_MAILBOX', ''),

            // Same host and version prefix as the send and inbox paths; the
            // resource differs per call (`/profile`, `/history`, `/messages`).
            'read_endpoint' => 'https://gmail.googleapis.com/gmail/v1/users',

            'scope' => 'https://www.googleapis.com/auth/gmail.readonly',

            // The same arithmetic as the inbox block, and the same quota page
            // (6,000 units per minute per user per project; `history.list` 2,
            // `messages.get` 20). Lower than the relay's hundred because each
            // message here is a `format=full` read that also costs 20 and
            // arrives on a five-minute clock rather than on a notification.
            'max_history_pages' => (int) env('GMAIL_SUPPORT_MAX_HISTORY_PAGES', 3),
            'max_messages_per_run' => (int) env('GMAIL_SUPPORT_MAX_MESSAGES', 50),

            // ⛔ **THE `authserv-id`s WHOSE `Authentication-Results` WE BELIEVE**
            // (4606). Support mail is routed to a tenant by its `From` header,
            // which the sender writes — so without this, anybody who knew an
            // owner's address could post into that tenant's thread as the owner.
            // `SupportMailbox` requires the provider's own header field to show
            // `dmarc=pass`, or `dkim=pass` with `header.d` aligned to the `From`
            // domain, before any address is resolved to an account.
            //
            // ⚠️ **MORE THAN ONE FIELD BEARING A TRUSTED ID IS REFUSED RATHER
            // THAN CHOSEN BETWEEN.** RFC 8601 §5 requires a conforming MTA to
            // delete forged instances bearing its own identifier, so a second
            // one means the assumption this rests on has failed — and the safe
            // reading of a failed assumption is that nothing is authenticated.
            //
            // ⚠️ **EMPTY DISABLES ROUTING ENTIRELY**, which is the fail-closed
            // direction: an unrecognised sender's mail waits in the mailbox for
            // a person, which is this path's designed fallback.
            'trusted_authserv_ids' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('GMAIL_SUPPORT_AUTHSERV_IDS', 'mx.google.com')),
            ))),

            // ⛔ **SEEDS OFF, LIKE ITS SIBLING.** Nothing may run until the
            // support account has been authorised with `gmail.readonly` and its
            // refresh token is in Ops → Platform → Credentials as
            // `gmail_support_refresh_token`.
            'enabled' => (bool) env('PLATFORM_MAIL_SUPPORT_INBOUND_ENABLED', false),
        ],

        /*
        |----------------------------------------------------------------------
        | Cloud Pub/Sub push — how we know a notification came from Google
        |----------------------------------------------------------------------
        |
        | ⚠️ **THIS IS A THIRD WEBHOOK AUTHENTICATION SHAPE AND IT IS LIKE
        | NEITHER OF THE OTHER TWO.** Infobip is an HMAC over the raw body with a
        | shared secret; SNS is an RSA signature whose certificate is named
        | inside the message. Pub/Sub is neither: the *body is not signed at
        | all*. Google attaches an OpenID Connect JWT in the `Authorization`
        | header, and the token says who is calling — never what they said.
        |
        | ⛔ **SO A VERIFIED REQUEST IS NOT A TRUSTED BODY.** Anybody who obtains
        | one of these tokens can post any body they like with it. That is why
        | `GmailPushController` treats the payload as nothing more than a
        | *prompt to go and look*: the actual mail is read back from Google over
        | our own authenticated connection, and the only field taken from the
        | request is the mailbox address, which is compared against ours and
        | discarded if it does not match.
        |
        | Quoted from `cloud.google.com/pubsub/docs/authenticate-push-
        | subscriptions` (read 2026-08-12, page dated 2026-07-30) — the five
        | checks it asks a receiving endpoint to make are the five below, plus
        | the expiry every JWT carries:
        |
        |   1. verify the signature against Google's published keys
        |   2. the `email` claim is the push service account we configured
        |   3. `email_verified` is true
        |   4. `aud` is this endpoint's own URL
        |   5. `iss` is Google
        |
        */

        'push' => [

            // ⛔ **BOTH OF THESE SEED EMPTY AND EITHER ONE EMPTY REFUSES EVERY
            // REQUEST.** `SnsMessageVerifier`'s topic allowlist and
            // `InfobipWebhookVerifier`'s signing key take the identical
            // position for the identical reason: the permissive branch here
            // leaves a route that can mark any message replied open to the
            // internet, and it would report as working.
            'audience' => env('GMAIL_PUSH_AUDIENCE', ''),
            'service_account' => env('GMAIL_PUSH_SERVICE_ACCOUNT', ''),

            // Google's published signing keys. ⚠️ **THE JWKS URI FROM THE
            // DISCOVERY DOCUMENT, NOT THE X.509 ONE.**
            // `accounts.google.com/.well-known/openid-configuration` names
            // `jwks_uri` as this, and `developers.google.com/identity/openid-
            // connect/openid-connect` tells an implementer to take the value
            // from there rather than hardcode a different one (both read
            // 2026-08-12). The legacy `oauth2/v1/certs` endpoint still answers
            // with ready-made PEM certificates and would have saved this
            // application a DER encoder — it is not what Google documents, and
            // `CLAUDE.md` records four vendor strings that were plausible and
            // wrong.
            'certificate_endpoint' => 'https://www.googleapis.com/oauth2/v3/certs',

            // ⚠️ **THE ISSUER IS HELD AS A BARE HOST AND THE TWO ACCEPTED FORMS
            // ARE BUILT FROM IT, WHICH IS THE SNS BLOCK'S RULE ONE FILE OVER.**
            // Written as `https://accounts.google.com` this would be read by
            // `outboundHostsInCode()` as a **destination**, and the subprocessor
            // inventory would then demand a row for a host nothing here dials —
            // §5 has it deliberately un-backticked as *"a redirect target that
            // appears in no file of ours"*, and backticking it in §1 would
            // assert a first-party call that does not exist. A validation value
            // is not an address, and this is the third time this repository has
            // had to say so.
            //
            // Google's OpenID Connect guide asks that the `iss` claim be
            // accepted as `https://accounts.google.com` **or** `accounts.google
            // .com`; both are derived below rather than one being guessed at.
            'issuer_host' => 'accounts.google.com',

            // ⚠️ **THE DOCUMENTATION WARNS THAT "TOKENS ATTACHED TO REQUESTS
            // SENT TO PUSH ENDPOINTS MAY BE UP TO AN HOUR OLD."** That is about
            // `iat`, not `exp`, so nothing here rejects an old-but-live token —
            // an endpoint that insisted on a fresh one would refuse a large
            // share of genuine deliveries. `exp` is enforced with a small
            // allowance for clock skew and nothing else is.
            'clock_skew_seconds' => 60,

            'certificate_timeout' => (int) env('GMAIL_PUSH_CERTIFICATE_TIMEOUT', 5),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | The sending domain (decision 2096, resolved)
    |--------------------------------------------------------------------------
    |
    | ⚠️ **THE VALUE IS NOT HERE. IT IS `mail.sending_domain` IN THE DEFAULTS
    | REGISTRY**, seeded `goaieasy.net` and enforced by `PlatformMailer`.
    | 2096 asked for a registry seed rather than a literal precisely so the next
    | reversal costs an Ops row instead of a deploy — and it has now been
    | reversed twice: decision 30's `reports.goaiez.com` fell to 2114's
    | `mail.goaiez.com` on 2026-08-11, which fell to 5500's `goaieasy.net` on
    | 2026-08-19 because a subdomain of the primary domain shares its
    | organizational reputation and is therefore not separation. The half that
    | survived every vendor change and both reversals is *never the primary
    | domain*, which `assertNotPrimaryDomain()` has enforced since 700.
    |
    | This block holds only what a config file should: the reply mailbox's local
    | part, which is a property of the mail server rather than of policy.
    |
    */

    'reply' => [

        // The local part a reply comes back to, plus-addressed with the
        // tracking code: `reply+8CHARCODE@goaieasy.net`. Plus-addressing
        // rather than a subdomain or a per-tenant mailbox because it needs one
        // mailbox and no DNS change, and because every mail client copies the
        // address it was sent from into a reply unchanged.
        'local_part' => env('PLATFORM_MAIL_REPLY_LOCAL_PART', 'reply'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Amazon SNS — the typed bounce and complaint feed
    |--------------------------------------------------------------------------
    |
    | ⚠️ **THE SIGNING CERTIFICATE'S HOST IS NOT WRITTEN AS A URL ANYWHERE, AND
    | THAT IS DELIBERATE.** SNS names the certificate in the message itself and
    | the host is region-segmented, so there is no literal to write and inventing
    | one is decision 431's guess. What is written is the *shape* an acceptable
    | one must have, held with no scheme so that `outboundHostsInCode()` does
    | not read a validation rule as a destination — that helper anchors on
    | `https?://`, so a bare host pattern is invisible to it. The vendor is
    | named in `SUBPROCESSOR-INVENTORY.md` §2 and exempted in `OutboundTest`,
    | which is Infobip's precedent for the identical problem.
    |
    | ⛔ **THE SHAPE WAS A PREFIX AND A SUFFIX UNTIL 2026-08-16 AND THAT ADMITS
    | AN ATTACKER-CONTROLLED S3 BUCKET** (4450): `sns.` + `.amazonaws.com`
    | matches `sns.evil.s3.amazonaws.com`, which is the case AWS's own validator
    | comment names in so many words. Both values below are now
    | `MessageValidator::validateUrl()` as AWS ships it
    | (`aws/aws-php-sns-message-validator`, read 2026-08-16) — one label between
    | `sns.` and the registrable domain, `(\.cn)?` for AWS China, and a URL that
    | ends in `.pem`.
    |
    */

    'sns' => [
        // AWS's `$defaultHostPattern`, character for character. Do not relax
        // the `[a-zA-Z0-9\-]{3,}` to allow a dot: that single character is the
        // whole difference between "a region" and "any bucket AWS hosts".
        //
        // ⚠️ TWO READERS, AND THE KEY IS DELIBERATELY NOT SPLIT IN TWO
        // (10220-10229). `SnsMessageVerifier` constrains `SigningCertURL` with
        // it, and `SnsSubscriptions` constrains the `SubscribeURL` an operator
        // completes a subscription through. What it describes is "an Amazon SNS
        // regional endpoint", which is what both URLs have to be; a second copy
        // under a second name is how the second one quietly stops matching the
        // first. Only the `.pem` suffix below is a fact about a certificate, and
        // only the verifier applies it.
        'certificate_host_pattern' => '/^sns\.[a-zA-Z0-9\-]{3,}\.amazonaws\.com(\.cn)?$/',

        // Compared against the whole URL, as AWS's own `substr($url, -4)` is.
        'certificate_url_suffix' => '.pem',

        // Which topics this endpoint will accept. AWS's own guidance: *"Reject
        // any message with an unexpected TopicArn to prevent spoofing"*
        // (docs.aws.amazon.com, Verifying the signatures of Amazon SNS
        // messages, read 2026-08-11). Empty means accept none, which is the
        // fail-closed direction and the state a fresh install is in.
        'topic_arns' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SES_SNS_TOPIC_ARNS', '')),
        ))),

        'certificate_timeout' => (int) env('SES_SNS_CERTIFICATE_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | AWS's own mailbox simulator — the only addresses `mail:probe-ses-simulator`
    | will ever send to (10280-10289)
    |--------------------------------------------------------------------------
    |
    | ⛔ **THE ONLY SUPPORTED WAY TO TEST THE `Notification` WIRE FORMAT AGAINST
    | REAL AWS BYTES, AND RUNNING IT IS WHAT SATISFIED THIS BULLET —
    | CORRECTED 2026-08-27 (10460–10479).** `SnsMessageVerifier`'s own
    | docblock: the `SubscriptionConfirmation` field list verified against
    | production traffic on 2026-08-26, and the `Notification` field list —
    | the one that carries every genuine bounce and complaint — verified
    | against real AWS bytes the same day, through this command: three probes
    | produced five real `Notification` messages (a bounce, and a complaint
    | and a delivery sharing one timestamp), all verified. ⛔ **What stays
    | true**: a genuine bounce from a real mailbox provider, rather than
    | AWS's own simulator, is still unproven and needs SES production access
    | and a real recipient — neither of which this file or any command in
    | this repository can produce.
    |
    | Verified against docs.aws.amazon.com/ses/latest/dg/
    | send-an-email-from-console.html ("Sending test emails in Amazon SES with
    | the simulator"), read 2026-08-26 — the live page, not memory:
    |
    |   success@simulator.amazonses.com          successful delivery
    |   bounce@simulator.amazonses.com            permanent bounce (SMTP 550
    |                                              5.1.1). NOT placed on AWS's
    |                                              own suppression list — AWS's
    |                                              own words — so a repeat send
    |                                              here never refuses at SES.
    |   ooto@simulator.amazonses.com               an RFC 3834 auto-response,
    |                                              sent back to the envelope
    |                                              sender. NOT an SNS
    |                                              notification of any kind —
    |                                              useless for this feed and
    |                                              listed only so the command's
    |                                              allowlist matches AWS's own
    |                                              table exactly rather than a
    |                                              subset chosen here.
    |   complaint@simulator.amazonses.com          an RFC 5965 complaint
    |   suppressionlist@simulator.amazonses.com    a hard bounce, as if the
    |                                              address were already on the
    |                                              account suppression list
    |
    | ⚠️ **THE SAME PAGE SAYS THE SIMULATOR "DOESN'T AFFECT YOUR DAILY SENDING
    | QUOTA" AND IS STILL BILLED THE SAME AS ANY OTHER MESSAGE** — corrected
    | here because an earlier draft of this feature's own brief said "spends
    | real money and real quota" and only the first half checks out against
    | AWS's page. It IS bound by the account's maximum sending RATE, which is
    | `mail.send_rate_per_second.smtp` above.
    |
    | ⛔ **FIXED, NOT ENV-DRIVEN.** These are AWS's own addresses, true for
    | every deployment that uses SES — there is nothing here for `.env` to
    | override, and an override would be the one way this allowlist could ever
    | drift from what AWS actually operates. A test empties this array directly
    | to prove the command fails closed with nothing configured, on the same
    | fail-closed reading as `sns.topic_arns` above.
    */
    'ses_simulator' => [
        'addresses' => [
            'success' => 'success@simulator.amazonses.com',
            'bounce' => 'bounce@simulator.amazonses.com',
            'ooto' => 'ooto@simulator.amazonses.com',
            'complaint' => 'complaint@simulator.amazonses.com',
            'suppressionlist' => 'suppressionlist@simulator.amazonses.com',
        ],
    ],
];
