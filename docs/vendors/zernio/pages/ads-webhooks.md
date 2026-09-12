# Ads webhooks

Receive an event when an ads account finishes its first sync, when a Meta Lead Gen form gets a lead, and when an ad object changes status.

Ads events cover the initial backfill after connecting an ads-capable account, real-time leads from Meta Lead Gen forms, and ad object status changes. Subscribe with `POST /v1/webhooks/settings` and the event names below ([first event](/webhooks#first-event)); delivery, retries and signatures are the same for every event ([how webhooks behave](/webhooks#how-it-behaves)).

## Events

| Event | Description |
| --- | --- |
| [`account.ads.initial_sync_completed`](#accountadsinitial_sync_completed) | The initial 90-day backfill completed for an ads-enabled account. Once per account. |
| [`lead.received`](#leadreceived) | A new lead was submitted against a Meta Lead Gen form. |
| [`ad.status_changed`](#adstatus_changed) | An ad, ad set or campaign changed status on the ad platform. Meta only. |

## How it behaves

### The initial sync reports success or failure once

Zernio runs the initial sync after an ads-capable account is connected: ad-account discovery plus a 90-day historical ad backfill. `sync` reports whether the backfill succeeded fully or partially and how many ads were synced versus failed. When scoping was applied at connect time ([scoping sync to specific ad accounts](/guides/connecting-accounts#scoping-sync-to-specific-ad-accounts)), `account.platformAdAccountId` echoes the chosen ad account when the scope is exactly one, and `account.platformAdAccountIds` lists every `act_*` synced.

On failure (`sync.status` is `"failure"`) the payload adds fields so you can branch without parsing prose: `sync.error` (the raw platform error, truncated to about 2 KB), `sync.errorCode` and `sync.errorSubcode` (platform-native codes when parseable, for example Meta `190` or `10`), and `sync.errorCategory`, a stable enum of `token_invalid`, `permission_denied`, `no_ad_accounts`, `rate_limited`, `discovery_failed` or `unknown`. New values may be added; existing ones are stable.

A failed sync is not final. Running the ads connect flow again ([Connect ads](/connect/connect-ads)) re-queues the 90-day backfill and the event fires again with the new outcome; Zernio skips the re-queue only when a backfill has already completed with at least one ad, or when one started less than 20 minutes ago. That is the fix for `token_invalid`, `permission_denied` and `rate_limited`. `no_ad_accounts` means discovery found no ad account on the grant, so reconnecting changes nothing until the user has one.

### Leads arrive in real time from Meta's Page webhook

Zernio ingests leads through the Page `leadgen` webhook and forwards each one as `lead.received`. `lead.fields` is the flattened question-key to answer map; for multiple-choice questions the value is the option key, for example `k1`, not the display label. `lead.formId`, `lead.adId` and `lead.campaignId` give provenance, and `lead.adId` is null for organic or test leads. Deduplicate on `lead.leadgenId` (Meta's lead id) or the event `id`.

Leads need ads enabled on the team, and the gate is a permission rather than a check at delivery time: Zernio asks for `leads_retrieval` on the Facebook consent screen only when the team has ads enabled, and Meta refuses the Page's `leadgen` subscription without it, so no lead ever arrives. A Facebook account connected before ads was enabled needs a [reconnect](/guides/connecting-accounts) to pick the permission up.

### Status changes come from two Meta fields

Zernio sources `ad.status_changed` from two Meta `ad_account` webhook fields. `in_process_ad_objects` means the object finished processing and left `IN_PROCESS`; `status.raw` carries Meta's `status_name` (`ACTIVE`, `PAUSED`, `PENDING_REVIEW`, `ARCHIVED`, `DELETED`, `DISAPPROVED`). `with_issues_ad_objects` means the object entered `WITH_ISSUES`; `status.raw` is `WITH_ISSUES` and `error` is filled from Meta's `error_code`, `error_summary` and `error_message`.

`adObject.level` is `CAMPAIGN`, `AD_SET` or `AD`; creative-level events are not forwarded. Branch on `status.raw`, and use `error.code` as the stable discriminator: `error.summary` and `error.message` are localized to the ad-account owner's Meta locale. `error` is present on most `WITH_ISSUES` events, can be absent because Meta does not always include diagnostics, and never appears on any other status, so null-check it before reading `error.code`.

Matching is keyed on `adObject.platformAdAccountId`. When several connected `metaads` accounts point at the same Meta ad account, each receives its own delivery.

Subscribing to `ad.status_changed` needs ads access on the team: without it `POST /v1/webhooks/settings` answers `403` with code `ads_addon_required`. Usage-based billing includes ads on every account ([pricing](/pricing)).

---

## `account.ads.initial_sync_completed`

The initial sync completed for an ads-enabled account: ad-account discovery plus the 90-day backfill. `sync` carries the outcome and, on failure, the error fields described above.

<br />

**Payload for `account.ads.initial_sync_completed`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: account.ads.initial_sync_completed
- **account** (required) `object`: 
  - **accountId** (required) `string`: The account's unique identifier (same as used in /v1/accounts/{accountId})
  - **profileId** (required) `string`: The profile's unique identifier this account belongs to
  - **platform** (required) `string`: No description
  - **username** (required) `string`: No description
  - **displayName** `string`: No description
  - **platformUserId** `string`: The platform-side account/ad-account ID (e.g. Meta ad account ID).
  - **profilePicture** `string` (uri): URL of the account's profile picture, when available.
  - **platformAdAccountId** `string`: When the consumer scoped the connect call to a single ad account, this echoes
that ID back so the webhook can be correlated to the originating connect
request without consulting the consumer's DB. Meta uses the `act_*` shape.
 (example: "act_1330190928038136")
  - **platformAdAccountIds** `array[string]`: Every ad-account ID that the connected token could see at discovery time.
Useful for "we synced ads from these accounts" UX without a follow-up API call.
Empty array when the token had no ad-account visibility.

- **sync** (required) `object`: Summary of the initial ads sync backfill results.
  - **status** (required) `string`: Overall outcome of the initial sync. - one of: success, failure
  - **totalAds** (required) `integer`: Total number of ads discovered for backfill.
  - **synced** (required) `integer`: Number of ads successfully synced.
  - **failed** (required) `integer`: Number of ads that failed to sync.
  - **error** `string`: Free-form error message from the platform (typically Meta's Marketing API).
Truncated to ~2KB. Present when `status` is `failure` (and sometimes on `success`
when discovery saw zero ad accounts). For UX branching prefer `errorCategory`;
this field is for human display and debugging.

  - **errorCode** `string`: Platform-native error code if parsed (e.g. Meta `190`, `10`, `200`).
  - **errorSubcode** `string`: Platform-native error subcode if parsed.
  - **errorCategory** `string`: Stable category for UX branching. New values may be added; existing ones are
stable. Mapping:
  - `token_invalid`: access token is expired or revoked. Reconnect.
  - `permission_denied`: token lacks required scope, or the user has no role
    on the Business Manager that owns the ad account. Reconnect with full
    permissions, or have an admin grant access.
  - `no_ad_accounts`: token is valid but sees zero ad accounts. The user
    needs to connect a Business Manager that owns ad accounts.
  - `rate_limited`: platform throttled us. Sync will retry automatically.
  - `discovery_failed`: any other platform-side failure. Inspect `error`.
  - `unknown`: classifier could not categorize the failure.
 - one of: token_invalid, permission_denied, no_ad_accounts, rate_limited, discovery_failed, unknown
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `lead.received`

A new lead was submitted against a Meta Lead Gen (Instant) form. `lead.fields` holds the answers keyed by question.

<br />

**Payload for `lead.received`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: lead.received
- **lead** (required) `object`: 
  - **id** (required) `string`: Zernio lead ID (AdLead document ID)
  - **leadgenId** (required) `string`: Meta lead ID (the platform's leadgen_id)
  - **formId** (required) `string`: Lead Gen form ID the lead was submitted against
  - **formName** `string,null`: Human-readable form name (best-effort; may be null)
  - **adId** `string,null`: Meta ad ID that drove the lead (null for organic/test leads)
  - **adsetId** `string,null`: No description
  - **campaignId** `string,null`: No description
  - **fields** (required) `object`: Flattened question key -> answer map. For multiple-choice questions the value is the option key (e.g. "k1"), not the display label.

  - **isOrganic** (required) `boolean`: True when the lead came from an organic post rather than a paid ad
  - **createdAt** (required) `string` (date-time): Meta's lead creation time (ISO 8601)
- **account** (required) `object`: 
  - **id** (required) `string`: Account ID (the facebook account owning the Page)
  - **accountId** `string`: Account ID (same as id); canonical field for account filtering.
  - **platform** (required) `string`: No description - one of: facebook
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `ad.status_changed`

A campaign, ad set or ad on a connected Meta ad account (`metaads`) changed status. `status.raw` is Meta's status name and `error` is present when the object entered `WITH_ISSUES`.

<br />

**Payload for `ad.status_changed`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: ad.status_changed
- **account** (required) `object`: The connected ad-platform account that owns the ad object.
  - **accountId** (required) `string`: Internal Zernio account ID (same as used in /v1/accounts/{accountId}).
  - **profileId** (required) `string`: Internal Zernio profile ID this account belongs to.
  - **platform** (required) `string`: Ad platform identifier. Currently always `metaads`. (example: "metaads")
  - **username** (required) `string`: Display username of the connected ad-platform account.
  - **displayName** `string`: Human-readable display name of the account, when available.
- **adObject** (required) `object`: The ad-platform object the status change applies to.
  - **level** (required) `string`: Hierarchy level the status applies to. Mirrors Meta's `level`. Creative-level events are not forwarded. - one of: CAMPAIGN, AD_SET, AD
  - **platformId** (required) `string`: Platform-native ID of the campaign / ad set / ad. For Meta this is
the bare numeric ID (e.g. `120244894077860689`).
 (example: "120244894077860689")
  - **platformAdAccountId** (required) `string`: Platform-native ad-account ID. For Meta this uses the `act_<id>`
shape.
 (example: "act_2129800524463520")
- **status** (required) `object`: Status info. Branch on `status.raw` to handle each transition.
  - **raw** (required) `string`: Platform-native status string, forwarded verbatim. For Meta
this is `status_name` from `in_process_ad_objects` (e.g.
`ACTIVE`, `PAUSED`, `PENDING_REVIEW`, `ARCHIVED`, `DELETED`,
`DISAPPROVED`), or `WITH_ISSUES` when sourced from
`with_issues_ad_objects`. Not constrained by an `enum`, because Meta
may add new values.
 (example: "ACTIVE")
- **error** `object`: Optional. Present on most `WITH_ISSUES` events, carrying the
platform's error diagnostics. May be absent on some `WITH_ISSUES`
events (Meta does not always include diagnostics). Always absent
for any other `status.raw` value. Always null-check before reading.

  - **code** (required) `string`: Platform-native error code, forwarded verbatim. For Meta this
is `error_code` as a string. Use as the stable discriminator, since
`summary` and `message` are localized.
 (example: "2643001")
  - **summary** `string`: Short human-readable summary (Meta `error_summary`). Localized
to the ad-account owner's Meta locale. Display only, do not
match on it.
 (example: "Ad Processing Error")
  - **message** `string`: Full human-readable error message (Meta `error_message`).
Localized, display only.

- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

## Related

- [Webhooks](/webhooks): create an endpoint, retries, signatures.
- [Meta Ads](/platforms/meta-ads): campaigns, boosting and lead forms.
- [Connecting accounts](/guides/connecting-accounts#scoping-sync-to-specific-ad-accounts): scope the sync to specific ad accounts.
- [List leads](/lead-gen/list-leads): the same leads on demand.

---
