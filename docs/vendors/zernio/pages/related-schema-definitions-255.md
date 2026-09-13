# Related Schema Definitions

## SocialAccount

### Properties

- **_id** (required) `string`: No description
- **platform** (required) `string`: No description - one of: tiktok, instagram, facebook, youtube, linkedin, twitter, threads, pinterest, reddit, bluesky, googlebusiness, telegram, snapchat, discord, slack, whatsapp, linkedinads, metaads, pinterestads, tiktokads, xads, googleads, openaiads, sms, phone, rcs
- **profileId** (required): No description
- **username** `string`: No description
- **displayName** `string`: No description
- **profilePicture** `string,null`: URL to the account's profile picture on the platform. May be null if the platform does not provide one.
- **profileUrl** `string`: Full profile URL for the connected account on its platform.
- **isActive** (required) `boolean`: No description
- **needsReconnection** `boolean`: The platform definitively reported the stored OAuth token as dead.
While true, GET /v1/connect/{platform}/ads returns a
fresh authUrl (implicit force=true) instead of alreadyConnected,
so re-running the connect flow recovers the account. Cleared
automatically when the account is re-authorized.

- **followersCount** `number`: Follower count (only included if user has analytics add-on)
- **followersLastUpdated** `string`: Last time follower count was updated (only included if user has analytics add-on)
- **parentAccountId** `string,null`: Reference to the parent posting SocialAccount. Set for ads accounts that share
or derive from a posting account's OAuth token. null for standalone ads (Google Ads)
and all posting accounts. Meta ads business-login accounts also have no parent.

- **enabled** `boolean`: Whether the user explicitly activated this account. false means the account was
created as a side effect (e.g., posting account auto-created when user connected
ads first). Such accounts are hidden from this list, cannot be posted to
(`ACCOUNT_NOT_ENABLED_FOR_POSTING`), and are not billed as connected accounts.

- **metadata** `object`: Platform-specific metadata. Fields vary by platform. For WhatsApp accounts, includes:
- qualityRating: Phone number quality rating from Meta (GREEN, YELLOW, RED, or UNKNOWN)
- nameStatus: Display name review status (APPROVED, PENDING_REVIEW, DECLINED, or NONE). A declined or pending display name does not by itself block sending; sendability is reported separately via health_status (can_send_message).
- messagingLimitTier: Maximum unique business-initiated conversations per 24h rolling window (TIER_250, TIER_1K, TIER_10K, TIER_100K, or TIER_UNLIMITED). Scales automatically as quality rating improves.
- verifiedName: Meta-verified business display name
- displayPhoneNumber: Formatted phone number (e.g., "+1 555-123-4567")
- wabaId: WhatsApp Business Account ID
- phoneNumberId: Meta phone number ID

For Meta ads business-login accounts:
- tokenType: system-user
- businessId: The owning Business Manager ID when there is one owner; null for multiple owners.
- businessIds: Owning Business Manager IDs discovered from granted ad accounts.
- grantedAdAccountIds: Ad-account IDs granted to the token.
- adAccountBusinesses: Map from ad-account ID to its owning business ID or null.
- availablePages: Granted Page IDs and names. No Page tokens are exposed.
- selectedPageId: The Page selected for creatives and lead forms, or null.
- scopedAdAccountIds: Existing sync scope preserved on reconnect.
Non-expiring tokens have no tokenExpiresAt field. Parent posting reconnects do not replace this token.

For LinkedIn accounts, profileData carries the profile details refreshed on each daily snapshot:
- profileData.bio: The member's headline for personal accounts, or the organization description for organization accounts. null when the member has not set one.
- profileData.extraData.vanityName: The member's profile slug, i.e. the /in/{vanityName} segment of profileUrl. Personal accounts only; an organization's own slug is in metadata.organizationInfo.vanityName.

---
