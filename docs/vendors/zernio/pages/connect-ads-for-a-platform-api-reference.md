# Connect ads for a platform API Reference

Unified ads connection endpoint. Creates a dedicated ads SocialAccount for the specified platform.

**Meta business login (opt-in).** Set `loginMode=business` for `facebook` or
`instagram` to use Facebook Login for Business and a Business Integration System User
token. No posting account is created or required. This mode always returns an authUrl;
it returns 503 when the server has no META_ADS_CONFIG_ID. Complete the dialog in a
browser. The callback creates or reconnects only the metaads account, preserving its
ID, history and scopedAdAccountIds. Non-empty successful subscription results replace
subscribedAdAccountIds to remove stale grants; an empty result leaves routing unchanged. A reconnect must grant
every previously scoped ad account (or every previous grant for an unscoped connection).
Missing or unverifiable grants return 409 before changing the account.

Pass `pageId` to select a granted Page for creatives and lead forms. API integrations
otherwise reuse the previous Page or sole granted Page. Multiple Pages without a selection
return 400 with available Page IDs for API integrations; restart with pageId.
Dashboard session logins use the sole current grant automatically or open the existing
Facebook Page picker for several grants, including reconnects. Selection completes
the Meta Ads connection. With no Pages granted the callback returns
400 with instructions to connect again and grant a Page.
Success redirects with connected=metaads, profileId and accountId.
Business login reports metadata.tokenType=system-user in GET /v1/accounts. An absent
Meta expires_in leaves tokenExpiresAt absent; no personal-token re-exchange occurs.
Subsequent classic requests can change the ad-account scope using the business token;
force=true requires loginMode=business to reconnect that connection.

**Same-token platforms (facebook, instagram, linkedin, pinterest).** The ads SocialAccount
(metaads, linkedinads, pinterestads) reuses the OAuth token of the parent posting account,
but only when an active parent exists and, for facebook and instagram, its stored token
carries ads_management and ads_read (linkedin and pinterest need no extra scope). In that
case no extra OAuth happens and the response is alreadyConnected: true.

When no such parent exists, or the scopes are missing, the endpoint returns an authUrl and a
full OAuth round trip is required. When a parent exists but carries no token usable for ad
accounts, the call fails with 400 RECONNECT_REQUIRED. Independently of the branch, the call
can return 403 ADS_ADDON_REQUIRED without the ads add-on and 402 PAYMENT_REQUIRED when the
billing gate is closed.

Meta Ads prerequisite: connecting Meta Ads (via facebook or instagram) requires a Facebook
Page. Not because the ad account is read through a Page, but because both parent posting
accounts are: the facebook flow only offers Pages you manage, and the instagram flow with
loginMethod=facebook_login only offers Instagram accounts linked to one of those Pages.
Without a Page there is no parent account to inherit a token from. A user who manages no
Facebook Page cannot complete this connection, and the facebook flow ends with
error=no_facebook_pages.

**Separate-token platforms (tiktok, twitter).** Starts the platform-specific marketing API
OAuth flow and creates an ads SocialAccount (tiktokads, xads) with its own token. If the ads
account already exists, returns alreadyConnected: true.
  - tiktok: accountId is OPTIONAL. With accountId, the new tiktokads account links to that posting account (parentAccountId set), so Spark Ads + standalone ads using the posting TT_USER identity become available. Without accountId, ads-only mode kicks in: the new tiktokads account has parentAccountId=null and standalone ads use a synthetic CUSTOMIZED_USER ("Brand Identity"); Spark Ads are unavailable because TikTok requires a posting account for them. The Brand Identity is configured separately via PATCH /v1/connect/tiktok-ads (or inline on POST /v1/ads/create via the brandIdentity field).
  - twitter (X Ads): accountId is REQUIRED. There's no ads-only mode, because tweets need to be authored by a real X user.

**Standalone platforms (googleads).** Starts the Google Ads OAuth flow and creates a
standalone ads SocialAccount (googleads) with no parent. If the account already exists,
returns alreadyConnected: true.

Ads accounts appear as regular SocialAccount documents with ads platform values (e.g., metaads, tiktokads) in GET /v1/accounts.


## GET /v1/connect/{platform}/ads

**Connect ads for a platform**

Unified ads connection endpoint. Creates a dedicated ads SocialAccount for the specified platform.

**Meta business login (opt-in).** Set `loginMode=business` for `facebook` or
`instagram` to use Facebook Login for Business and a Business Integration System User
token. No posting account is created or required. This mode always returns an authUrl;
it returns 503 when the server has no META_ADS_CONFIG_ID. Complete the dialog in a
browser. The callback creates or reconnects only the metaads account, preserving its
ID, history and scopedAdAccountIds. Non-empty successful subscription results replace
subscribedAdAccountIds to remove stale grants; an empty result leaves routing unchanged. A reconnect must grant
every previously scoped ad account (or every previous grant for an unscoped connection).
Missing or unverifiable grants return 409 before changing the account.

Pass `pageId` to select a granted Page for creatives and lead forms. API integrations
otherwise reuse the previous Page or sole granted Page. Multiple Pages without a selection
return 400 with available Page IDs for API integrations; restart with pageId.
Dashboard session logins use the sole current grant automatically or open the existing
Facebook Page picker for several grants, including reconnects. Selection completes
the Meta Ads connection. With no Pages granted the callback returns
400 with instructions to connect again and grant a Page.
Success redirects with connected=metaads, profileId and accountId.
Business login reports metadata.tokenType=system-user in GET /v1/accounts. An absent
Meta expires_in leaves tokenExpiresAt absent; no personal-token re-exchange occurs.
Subsequent classic requests can change the ad-account scope using the business token;
force=true requires loginMode=business to reconnect that connection.

**Same-token platforms (facebook, instagram, linkedin, pinterest).** The ads SocialAccount
(metaads, linkedinads, pinterestads) reuses the OAuth token of the parent posting account,
but only when an active parent exists and, for facebook and instagram, its stored token
carries ads_management and ads_read (linkedin and pinterest need no extra scope). In that
case no extra OAuth happens and the response is alreadyConnected: true.

When no such parent exists, or the scopes are missing, the endpoint returns an authUrl and a
full OAuth round trip is required. When a parent exists but carries no token usable for ad
accounts, the call fails with 400 RECONNECT_REQUIRED. Independently of the branch, the call
can return 403 ADS_ADDON_REQUIRED without the ads add-on and 402 PAYMENT_REQUIRED when the
billing gate is closed.

Meta Ads prerequisite: connecting Meta Ads (via facebook or instagram) requires a Facebook
Page. Not because the ad account is read through a Page, but because both parent posting
accounts are: the facebook flow only offers Pages you manage, and the instagram flow with
loginMethod=facebook_login only offers Instagram accounts linked to one of those Pages.
Without a Page there is no parent account to inherit a token from. A user who manages no
Facebook Page cannot complete this connection, and the facebook flow ends with
error=no_facebook_pages.

**Separate-token platforms (tiktok, twitter).** Starts the platform-specific marketing API
OAuth flow and creates an ads SocialAccount (tiktokads, xads) with its own token. If the ads
account already exists, returns alreadyConnected: true.
  - tiktok: accountId is OPTIONAL. With accountId, the new tiktokads account links to that posting account (parentAccountId set), so Spark Ads + standalone ads using the posting TT_USER identity become available. Without accountId, ads-only mode kicks in: the new tiktokads account has parentAccountId=null and standalone ads use a synthetic CUSTOMIZED_USER ("Brand Identity"); Spark Ads are unavailable because TikTok requires a posting account for them. The Brand Identity is configured separately via PATCH /v1/connect/tiktok-ads (or inline on POST /v1/ads/create via the brandIdentity field).
  - twitter (X Ads): accountId is REQUIRED. There's no ads-only mode, because tweets need to be authored by a real X user.

**Standalone platforms (googleads).** Starts the Google Ads OAuth flow and creates a
standalone ads SocialAccount (googleads) with no parent. If the account already exists,
returns alreadyConnected: true.

Ads accounts appear as regular SocialAccount documents with ads platform values (e.g., metaads, tiktokads) in GET /v1/accounts.


### Parameters

- **loginMode** (optional) in query: Meta ads authorization mode. Business login is opt-in for Facebook and Instagram; classic preserves the posting-account flow.
- **pageId** (optional) in query: Business login only. Facebook Page ID to select from the token grants for ad creatives and lead forms.
- **platform** (required) in path: Platform to connect ads for. Only platforms with ads support are accepted.

In classic mode, `instagram` requires an Instagram account connected with loginMethod=facebook_login whose
token carries ads_management and ads_read. With an account connected through the default
instagram_login flow no ads account can be created; do not use this value for those accounts.

- **profileId** (required) in query: Your Zernio profile ID
- **accountId** (optional) in query: Existing SocialAccount ID. Required for `twitter` (X Ads). Optional for `tiktok`:
omit to enter ads-only mode (no TikTok posting account linked; ad creation uses
a Brand Identity instead of a TT_USER). Ignored for same-token (`facebook`,
`instagram`, `linkedin`, `pinterest`) and standalone (`googleads`) platforms.

- **redirect_url** (optional) in query: Custom URL the browser is sent to once the OAuth flow finishes. Honored on
every ads platform, including the separate-token (`tiktok`, `twitter`) and
standalone (`googleads`) flows. MUST be an absolute http(s) URL or a custom
app scheme for mobile deeplinks (e.g. myapp://callback); a relative path is
rejected with 400 INVALID_REDIRECT_URL. On success
`tiktok`, `twitter` and `googleads` land on the URL unchanged, while the
same-token platforms (`facebook`, `instagram`, `linkedin`, `pinterest`)
append `connected`, `profileId`, `accountId`, `username` and, on API-key
calls, `connect_token`. On failure the same error contract applies as on
GET /v1/connect/{platform}: `error` and `platform` are always appended,
other params are optional, and the value list there is not exhaustive.
On the tiktok, twitter and googleads flows `platform` carries
the ads platform id (`tiktokads`, `xads`, `googleads`), not the value
used in the request path. When omitted, the browser lands on
the Zernio dashboard.

- **headless** (optional) in query: Enable headless mode (same-token platforms only)
- **force** (optional) in query: Force a fresh OAuth even when an account already exists. Normally the
endpoint returns `alreadyConnected: true` whenever a connected account
is found, keying off its active state rather than token liveness.
Set `force=true` to bypass that and always receivean `authUrl`.
Completing the returned OAuth refreshes the stored token
on the existing posting and ads accounts in place.

- **adAccountId** (optional) in query: Scope ad sync to a single platform ad account. Without this param,
sync covers every ad account the connected token can see. Business-login reconnects
preserve the existing scope; supplied IDs are checked against the new grant. To change
that scope after migration, call this endpoint with the IDs and omit loginMode. Supported
on `facebook`/`instagram` (Meta, `act_<digits>`), `linkedin` (bare
numeric sponsored-account id), `googleads` (bare customer id digits)
and `twitter` (X Ads, base36 account id). `tiktok` scopes advertisers
at OAuth and `pinterest` has no ads discovery, so both ignore it.
Meta ids are additionally validated against the connected token;
unreachable IDs return 400. Setting a scope also removes already
synced ads from de-scoped ad accounts. For multiple accounts use
`adAccountIds` instead.

- **adAccountIds** (optional) in query: Scope ad sync to multiple platform ad accounts (same platform
support and id shapes as `adAccountId`). Repeat the param
(`?adAccountIds=act_1&adAccountIds=act_2`) or comma-separate
(`?adAccountIds=act_1,act_2`). Persisted server-side; latest call
wins, and de-scoped ad accounts have their synced ads removed.
Omitting both `adAccountId` and `adAccountIds` keeps any previously
persisted scope unchanged.


### Responses

#### 200: Either an OAuth URL to redirect to, or confirmation that ads are already connected

**Response Body:**

*One of the following:*
  - **alreadyConnected** `boolean`: No description (example: true)
  - **accountId** `string`: No description
  - **platform** `string`: No description
  - **username** `string`: No description
  - **displayName** `string`: No description
  - **tokenType** `string`: Present for an existing business-login connection. - one of: system-user
  - **scopedAdAccountIds** `array[string]`: Echo of the persisted ad-account scope when the caller passed
`adAccountId` / `adAccountIds`. Omitted when no scope is set.

  - **authUrl** `string` (uri): No description
  - **state** `string`: No description

#### 400: Platform doesn't support ads, missing accountId for X Ads, or a non-absolute redirect_url

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads access required (Ads add-on on legacy plans, included on usage-based plans), or no access to profile

#### 404: Profile or posting account not found

#### 409: Reconnect a system-user connection with loginMode=business.

#### 503: Business login is not configured or the platform is temporarily unavailable.

---

---
