# Zernio API — endpoint inventory

Generated 2026-09-12 from https://zernio.com/openapi.json (title: Zernio API, version 1.0.4, OpenAPI 3.1.0). Base URL: https://zernio.com/api (all paths versioned under /v1). Auth: Bearer API key. The full spec is `openapi.json` beside this file; the docs site is https://docs.zernio.com (pages saved under `docs/`).

Paths: 484 · operations: 723 · tags: 68

Security schemes: `bearerAuth` (http bearer  ), `connectToken` (apiKey  header X-Connect-Token)


## API Keys (4)

- `GET /v1/api-keys` — List keys
- `POST /v1/api-keys` — Create key
- `DELETE /v1/api-keys/{keyId}` — Delete key
- `GET /v1/auth/verify` — Verify credential

## Account Groups (4)

- `GET /v1/account-groups` — List groups
- `POST /v1/account-groups` — Create group
- `DELETE /v1/account-groups/{groupId}` — Delete group
- `PUT /v1/account-groups/{groupId}` — Update group

## Account Settings (9)

- `DELETE /v1/accounts/{accountId}/instagram-ice-breakers` — Delete IG ice breakers
- `GET /v1/accounts/{accountId}/instagram-ice-breakers` — Get IG ice breakers
- `PUT /v1/accounts/{accountId}/instagram-ice-breakers` — Set IG ice breakers
- `DELETE /v1/accounts/{accountId}/messenger-menu` — Delete FB persistent menu
- `GET /v1/accounts/{accountId}/messenger-menu` — Get FB persistent menu
- `PUT /v1/accounts/{accountId}/messenger-menu` — Set FB persistent menu
- `DELETE /v1/accounts/{accountId}/telegram-commands` — Delete TG bot commands
- `GET /v1/accounts/{accountId}/telegram-commands` — Get TG bot commands
- `PUT /v1/accounts/{accountId}/telegram-commands` — Set TG bot commands

## Accounts (14)

- `GET /v1/accounts` — List accounts
- `GET /v1/accounts/follower-stats` — Get follower stats
- `GET /v1/accounts/health` — Check accounts health
- `DELETE /v1/accounts/{accountId}` — Disconnect account
- `PATCH /v1/accounts/{accountId}` — Move account to another profile
- `PUT /v1/accounts/{accountId}` — Update account
- `GET /v1/accounts/{accountId}/bluesky-settings` — Get Bluesky account settings
- `PATCH /v1/accounts/{accountId}/bluesky-settings` — Update Bluesky account settings
- `GET /v1/accounts/{accountId}/follow-status/{userId}` — Check whether an Instagram user follows the account
- `GET /v1/accounts/{accountId}/health` — Check account health
- `GET /v1/accounts/{accountId}/posts` — List posts published on the platform
- `GET /v1/accounts/{accountId}/slack-settings` — Get Slack account settings
- `PATCH /v1/accounts/{accountId}/slack-settings` — Update Slack account settings
- `GET /v1/accounts/{accountId}/tiktok/creator-info` — Get TikTok creator info

## Ad Accounts (46)

- `GET /v1/accounts/{accountId}/custom-conversions` — List custom conversions
- `POST /v1/accounts/{accountId}/custom-conversions` — Create custom conversion
- `GET /v1/ads/accounts` — List ad accounts
- `PATCH /v1/ads/accounts` — Update ad account settings
- `POST /v1/ads/accounts` — Create Meta ad account
- `DELETE /v1/ads/accounts/callouts` — Remove account callout
- `GET /v1/ads/accounts/callouts` — List account callouts
- `POST /v1/ads/accounts/callouts` — Add account callouts
- `PUT /v1/ads/accounts/callouts` — Update account callouts
- `GET /v1/ads/accounts/finance` — Ad account finances
- `GET /v1/ads/accounts/negative-keyword-lists` — List negative keyword lists
- `POST /v1/ads/accounts/negative-keyword-lists` — Create a negative keyword list
- `DELETE /v1/ads/accounts/negative-keyword-lists/{listId}` — Delete a negative keyword list
- `GET /v1/ads/accounts/negative-keyword-lists/{listId}` — Get a negative keyword list
- `PUT /v1/ads/accounts/negative-keyword-lists/{listId}` — Rename a negative keyword list
- `PUT /v1/ads/accounts/negative-keyword-lists/{listId}/keywords` — Replace negative list keywords
- `DELETE /v1/ads/accounts/sitelinks` — Remove account sitelink
- `GET /v1/ads/accounts/sitelinks` — List account sitelinks
- `POST /v1/ads/accounts/sitelinks` — Add account sitelinks
- `PUT /v1/ads/accounts/sitelinks` — Update account sitelinks
- `DELETE /v1/ads/accounts/structured-snippets` — Remove account snippet
- `GET /v1/ads/accounts/structured-snippets` — List account snippets
- `POST /v1/ads/accounts/structured-snippets` — Add account snippets
- `PUT /v1/ads/accounts/structured-snippets` — Update account snippets
- `GET /v1/ads/activity` — Ad account change / audit log
- `GET /v1/ads/advertisable-applications` — List advertisable apps
- `GET /v1/ads/business-centers` — List TikTok Business Centers
- `GET /v1/ads/businesses` — Businesses list
- `GET /v1/ads/dsa-defaults` — Get ad account DSA defaults
- `GET /v1/ads/dsa-recommendations` — Get DSA recommendations
- `GET /v1/ads/high-demand-periods` — List high-demand periods
- `POST /v1/ads/high-demand-periods` — Schedule a budget increase
- `GET /v1/ads/instagram-accounts` — List Instagram ad identities
- `GET /v1/ads/ios-fourteen-campaign-limits` — Get iOS 14 campaign limits
- `GET /v1/ads/labels` — Ad labels
- `GET /v1/ads/pixels` — List TikTok ad pixels
- `GET /v1/ads/studies` — A/B tests and lift studies
- `GET /v1/ads/value-rule-sets` — List value rule sets
- `POST /v1/ads/value-rule-sets` — Create a value rule set
- `DELETE /v1/ads/value-rule-sets/{valueRuleSetId}` — Delete a value rule set
- `GET /v1/ads/value-rule-sets/{valueRuleSetId}` — Read a value rule set
- `PUT /v1/ads/value-rule-sets/{valueRuleSetId}` — Replace a value rule set
- `GET /v1/ads/{adId}/comments` — List comments on an ad
- `DELETE /v1/ads/{adId}/comments/{commentId}` — Delete an ad comment
- `POST /v1/ads/{adId}/comments/{commentId}/hide` — Hide or unhide an ad comment
- `POST /v1/ads/{adId}/comments/{commentId}/reply` — Reply to an ad comment

## Ad Audiences (7)

- `GET /v1/ads/audiences` — List custom audiences
- `POST /v1/ads/audiences` — Create custom audience
- `DELETE /v1/ads/audiences/{audienceId}` — Delete custom audience
- `GET /v1/ads/audiences/{audienceId}` — Get audience details
- `PUT /v1/ads/audiences/{audienceId}` — Update an audience
- `POST /v1/ads/audiences/{audienceId}/companies` — Replace audience companies
- `POST /v1/ads/audiences/{audienceId}/users` — Add users to audience

## Ad Campaigns (48)

- `GET /v1/ads` — List ads
- `GET /v1/ads/ad-sets` — List ad sets
- `POST /v1/ads/ad-sets` — Create a standalone ad group
- `DELETE /v1/ads/ad-sets/{adSetId}` — Delete an ad set
- `GET /v1/ads/ad-sets/{adSetId}` — Get live ad-set details
- `PUT /v1/ads/ad-sets/{adSetId}` — Update an ad set
- `DELETE /v1/ads/ad-sets/{adSetId}/assets` — Remove ad-group assets
- `GET /v1/ads/ad-sets/{adSetId}/assets` — List ad-group assets
- `POST /v1/ads/ad-sets/{adSetId}/assets` — Attach ad-group assets
- `PUT /v1/ads/ad-sets/{adSetId}/assets` — Update ad-group assets
- `POST /v1/ads/ad-sets/{adSetId}/duplicate` — Duplicate an ad set
- `PUT /v1/ads/ad-sets/{adSetId}/status` — Pause or resume a single ad set
- `GET /v1/ads/bid-strategies` — List portfolio bid strategies
- `POST /v1/ads/bid-strategies` — Create portfolio bid strategy
- `PATCH /v1/ads/bid-strategies/{strategyId}` — Update portfolio bid strategy
- `POST /v1/ads/boost` — Boost post as ad
- `GET /v1/ads/campaigns` — List campaigns
- `POST /v1/ads/campaigns` — Create a standalone campaign
- `POST /v1/ads/campaigns/bulk-status` — Pause or resume many campaigns
- `DELETE /v1/ads/campaigns/{campaignId}` — Delete a campaign
- `GET /v1/ads/campaigns/{campaignId}` — Get live campaign details
- `PUT /v1/ads/campaigns/{campaignId}` — Update a campaign
- `GET /v1/ads/campaigns/{campaignId}/asset-groups` — List Performance Max asset groups
- `DELETE /v1/ads/campaigns/{campaignId}/assets` — Remove campaign assets
- `GET /v1/ads/campaigns/{campaignId}/assets` — List campaign assets
- `POST /v1/ads/campaigns/{campaignId}/assets` — Attach campaign assets
- `PUT /v1/ads/campaigns/{campaignId}/assets` — Update campaign assets
- `GET /v1/ads/campaigns/{campaignId}/bidding` — Read a campaign's current bidding
- `POST /v1/ads/campaigns/{campaignId}/duplicate` — Duplicate a campaign
- `GET /v1/ads/campaigns/{campaignId}/negative-keyword-lists` — List campaign negative lists
- `PUT /v1/ads/campaigns/{campaignId}/negative-keyword-lists` — Replace campaign negative lists
- `GET /v1/ads/campaigns/{campaignId}/negative-keywords` — List campaign-level negative keywords
- `PUT /v1/ads/campaigns/{campaignId}/negative-keywords` — Replace campaign-level negative keywords
- `PUT /v1/ads/campaigns/{campaignId}/status` — Pause or resume a campaign
- `GET /v1/ads/campaigns/{campaignId}/targeting` — Read a Google campaign's device, location, and language targeting
- `PUT /v1/ads/campaigns/{campaignId}/targeting` — Edit a Google campaign's device, location, or language targeting
- `POST /v1/ads/create` — Create standalone ad
- `GET /v1/ads/keywords` — List Search keywords
- `POST /v1/ads/keywords` — Add Search ad-group keywords
- `DELETE /v1/ads/keywords/{keywordId}` — Remove a Search keyword
- `PATCH /v1/ads/keywords/{keywordId}` — Pause or enable a Search keyword
- `GET /v1/ads/timeline` — Get daily account metrics
- `GET /v1/ads/tree` — Get campaign tree
- `DELETE /v1/ads/{adId}` — Cancel an ad
- `GET /v1/ads/{adId}` — Get ad details
- `PUT /v1/ads/{adId}` — Update ad
- `POST /v1/ads/{adId}/duplicate` — Duplicate an ad
- `PUT /v1/ads/{adId}/status` — Pause or resume a single ad

## Ad Creatives (18)

- `GET /v1/ads/catalogs` — List Meta product catalogs
- `GET /v1/ads/catalogs/{catalogId}/product-sets` — List a catalog's product sets
- `GET /v1/ads/creatives` — Creative library
- `POST /v1/ads/creatives` — Create a standalone creative
- `DELETE /v1/ads/creatives/{creativeId}` — Delete a creative
- `GET /v1/ads/creatives/{creativeId}` — Creative details
- `PUT /v1/ads/creatives/{creativeId}` — Rename a creative
- `GET /v1/ads/images` — Ad image library
- `POST /v1/ads/images` — Upload an ad image from base64
- `GET /v1/ads/partnership-content` — List partnership ad content
- `GET /v1/ads/partnership-permissions` — List partnership permissions
- `POST /v1/ads/partnership-permissions` — Set partnership permission
- `POST /v1/ads/preview` — Render pre-create ad previews
- `GET /v1/ads/videos` — Ad video library
- `POST /v1/ads/videos` — Upload an ad video
- `DELETE /v1/ads/videos/{videoId}` — Delete an ad video
- `GET /v1/ads/{adId}/media` — Direct video and image URLs for an ad
- `GET /v1/ads/{adId}/preview` — Render previews of an existing ad

## Ad Insights (10)

- `GET /v1/ads/campaigns/{campaignId}/analytics` — Get campaign analytics
- `GET /v1/ads/insights` — Flexible live insights query
- `POST /v1/ads/insights/reports` — Submit async insights report
- `GET /v1/ads/insights/reports/{reportRunId}` — Poll an async insights report run
- `POST /v1/ads/keywords/historical-metrics` — Get historical keyword metrics
- `POST /v1/ads/keywords/ideas` — Generate keyword ideas
- `GET /v1/ads/local-services/leads` — Google Local Services Ads leads
- `GET /v1/ads/local-services/leads/{leadId}/conversations` — List lead conversations
- `GET /v1/ads/search-terms` — Google Ads search terms report
- `GET /v1/ads/{adId}/analytics` — Get ad analytics

## Ad Library (1)

- `GET /v1/ads/library` — Search the public Ad Library

## Ad Targeting (5)

- `GET /v1/ads/interests` — Search targeting interests **(deprecated)**
- `POST /v1/ads/targeting/bid-pricing` — Suggested bid and budget bounds
- `POST /v1/ads/targeting/reach-estimate` — Estimate audience reach
- `GET /v1/ads/targeting/search` — Search targeting options
- `POST /v1/ads/targeting/supply-forecast` — Forecast ad delivery

## Analytics (26)

- `GET /v1/accounts/follower-stats` — Get follower stats
- `GET /v1/accounts/{accountId}/facebook-post-reactions` — Get Facebook post reactions
- `GET /v1/accounts/{accountId}/linkedin-aggregate-analytics` — Get LinkedIn aggregate stats
- `GET /v1/accounts/{accountId}/linkedin-post-analytics` — Get LinkedIn post stats
- `GET /v1/accounts/{accountId}/linkedin-post-reactions` — Get LinkedIn post reactions
- `GET /v1/analytics` — Get post analytics
- `GET /v1/analytics/best-time` — Get best times to post
- `GET /v1/analytics/content-decay` — Get content performance decay
- `GET /v1/analytics/daily-metrics` — Get daily aggregated metrics
- `GET /v1/analytics/delta` — Analytics changed since a cursor
- `GET /v1/analytics/facebook/page-insights` — Get Facebook Page insights
- `GET /v1/analytics/facebook/post-earnings` — Get Facebook post monetization earnings
- `GET /v1/analytics/googlebusiness/performance` — Get Google Business Profile performance metrics
- `GET /v1/analytics/googlebusiness/search-keywords` — Get Google Business Profile search keywords
- `GET /v1/analytics/instagram/account-insights` — Get Instagram insights
- `GET /v1/analytics/instagram/demographics` — Get Instagram demographics
- `GET /v1/analytics/instagram/follower-history` — Get Instagram follower history
- `GET /v1/analytics/linkedin/org-aggregate-analytics` — Get LinkedIn org analytics
- `GET /v1/analytics/post-timeline` — Get post analytics timeline
- `GET /v1/analytics/posting-frequency` — Get frequency vs engagement
- `GET /v1/analytics/tiktok/account-insights` — Get TikTok account-level insights
- `GET /v1/analytics/youtube/channel-insights` — Get YouTube channel insights
- `GET /v1/analytics/youtube/daily-views` — Get YouTube daily views
- `GET /v1/analytics/youtube/demographics` — Get YouTube demographics
- `GET /v1/analytics/youtube/video-retention` — Get YouTube video retention curve
- `POST /v1/posts/sync-external` — Sync an external post

## Blogs (10)

- `GET /v1/accounts/{accountId}/blogs` — List blogs
- `POST /v1/accounts/{accountId}/blogs` — Create a blog
- `DELETE /v1/accounts/{accountId}/blogs/{blogId}` — Delete a blog
- `GET /v1/accounts/{accountId}/blogs/{blogId}` — Get a blog
- `PATCH /v1/accounts/{accountId}/blogs/{blogId}` — Update a blog
- `GET /v1/accounts/{accountId}/blogs/{blogId}/articles` — List blog articles
- `POST /v1/accounts/{accountId}/blogs/{blogId}/articles` — Create a blog article
- `DELETE /v1/accounts/{accountId}/blogs/{blogId}/articles/{articleId}` — Delete a blog article
- `GET /v1/accounts/{accountId}/blogs/{blogId}/articles/{articleId}` — Get a blog article
- `PATCH /v1/accounts/{accountId}/blogs/{blogId}/articles/{articleId}` — Update a blog article

## Broadcasts (10)

- `GET /v1/broadcasts` — List broadcasts
- `POST /v1/broadcasts` — Create broadcast draft
- `DELETE /v1/broadcasts/{broadcastId}` — Delete broadcast
- `GET /v1/broadcasts/{broadcastId}` — Get broadcast details
- `PATCH /v1/broadcasts/{broadcastId}` — Update broadcast
- `POST /v1/broadcasts/{broadcastId}/cancel` — Cancel broadcast
- `GET /v1/broadcasts/{broadcastId}/recipients` — List broadcast recipients
- `POST /v1/broadcasts/{broadcastId}/recipients` — Add recipients to a broadcast
- `POST /v1/broadcasts/{broadcastId}/schedule` — Schedule broadcast for later
- `POST /v1/broadcasts/{broadcastId}/send` — Send broadcast now

## Business Agent (55)

- `GET /v1/accounts/{accountId}/business-agent` — Get agent setup status
- `GET /v1/accounts/{accountId}/business-agent/allowlist` — List allowlisted consumers
- `POST /v1/accounts/{accountId}/business-agent/allowlist` — Allowlist a consumer
- `DELETE /v1/accounts/{accountId}/business-agent/allowlist/{entryId}` — Remove an allowlisted consumer
- `GET /v1/accounts/{accountId}/business-agent/budget` — Get usage budgets
- `PUT /v1/accounts/{accountId}/business-agent/budget` — Replace usage budgets
- `DELETE /v1/accounts/{accountId}/business-agent/business-information` — Reset business information
- `GET /v1/accounts/{accountId}/business-agent/business-information` — Get business information
- `PUT /v1/accounts/{accountId}/business-agent/business-information` — Replace business information
- `GET /v1/accounts/{accountId}/business-agent/connectors` — List connectors
- `POST /v1/accounts/{accountId}/business-agent/connectors` — Create a connector
- `DELETE /v1/accounts/{accountId}/business-agent/connectors/{connectorId}` — Delete a connector
- `GET /v1/accounts/{accountId}/business-agent/connectors/{connectorId}` — Get a connector
- `PUT /v1/accounts/{accountId}/business-agent/connectors/{connectorId}` — Update a connector
- `POST /v1/accounts/{accountId}/business-agent/connectors/{connectorId}/credentials` — Set connector credentials
- `GET /v1/accounts/{accountId}/business-agent/connectors/{connectorId}/logs` — Get connector failure logs
- `POST /v1/accounts/{accountId}/business-agent/connectors/{connectorId}/refresh-tools` — Refresh MCP connector tools
- `GET /v1/accounts/{accountId}/business-agent/connectors/{connectorId}/tools` — List connector tools
- `POST /v1/accounts/{accountId}/business-agent/connectors/{connectorId}/tools` — Create a connector tool
- `DELETE /v1/accounts/{accountId}/business-agent/connectors/{connectorId}/tools/{toolId}` — Delete a connector tool
- `GET /v1/accounts/{accountId}/business-agent/connectors/{connectorId}/tools/{toolId}` — Get a connector tool
- `PUT /v1/accounts/{accountId}/business-agent/connectors/{connectorId}/tools/{toolId}` — Update a connector tool
- `POST /v1/accounts/{accountId}/business-agent/connectors/{connectorId}/tools/{toolId}/run` — Run a connector tool once
- `GET /v1/accounts/{accountId}/business-agent/evals` — Read evaluation data
- `POST /v1/accounts/{accountId}/business-agent/evals` — Start an evaluation run
- `POST /v1/accounts/{accountId}/business-agent/events` — Send a business event
- `GET /v1/accounts/{accountId}/business-agent/events/{eventId}` — Get a business event status
- `GET /v1/accounts/{accountId}/business-agent/faqs` — List FAQs
- `POST /v1/accounts/{accountId}/business-agent/faqs` — Create a FAQ
- `DELETE /v1/accounts/{accountId}/business-agent/faqs/{faqId}` — Delete a FAQ
- `GET /v1/accounts/{accountId}/business-agent/faqs/{faqId}` — Get a FAQ
- `PUT /v1/accounts/{accountId}/business-agent/faqs/{faqId}` — Update a FAQ
- `GET /v1/accounts/{accountId}/business-agent/files` — List knowledge files
- `POST /v1/accounts/{accountId}/business-agent/files` — Upload a knowledge file
- `DELETE /v1/accounts/{accountId}/business-agent/files/{fileId}` — Delete a knowledge file
- `GET /v1/accounts/{accountId}/business-agent/files/{fileId}` — Get a knowledge file
- `POST /v1/accounts/{accountId}/business-agent/onboard` — Create the agent
- `GET /v1/accounts/{accountId}/business-agent/settings` — List agent settings
- `PATCH /v1/accounts/{accountId}/business-agent/settings` — Update agent settings
- `GET /v1/accounts/{accountId}/business-agent/skills` — List skills
- `POST /v1/accounts/{accountId}/business-agent/skills` — Create a skill
- `DELETE /v1/accounts/{accountId}/business-agent/skills/{skillId}` — Delete a skill
- `GET /v1/accounts/{accountId}/business-agent/skills/{skillId}` — Get a skill
- `PUT /v1/accounts/{accountId}/business-agent/skills/{skillId}` — Update a skill
- `POST /v1/accounts/{accountId}/business-agent/test-messages` — Send a test message
- `GET /v1/accounts/{accountId}/business-agent/ui-skills` — List UI skills
- `POST /v1/accounts/{accountId}/business-agent/ui-skills` — Create a UI skill
- `DELETE /v1/accounts/{accountId}/business-agent/ui-skills/{uiSkillId}` — Delete a UI skill
- `GET /v1/accounts/{accountId}/business-agent/ui-skills/{uiSkillId}` — Get a UI skill
- `PUT /v1/accounts/{accountId}/business-agent/ui-skills/{uiSkillId}` — Update a UI skill
- `GET /v1/accounts/{accountId}/business-agent/websites` — List crawled websites
- `POST /v1/accounts/{accountId}/business-agent/websites` — Add a website to crawl
- `DELETE /v1/accounts/{accountId}/business-agent/websites/{websiteId}` — Remove a crawled website
- `GET /v1/accounts/{accountId}/business-agent/websites/{websiteId}` — Get a crawled website
- `PUT /v1/accounts/{accountId}/business-agent/websites/{websiteId}` — Update a crawled website

## Calls (3)

- `GET /v1/calls` — List all calls (unified history)
- `GET /v1/calls/{id}` — Get a call (any channel)
- `GET /v1/calls/{id}/recording` — Get a call recording

## Comment Automations (6)

- `GET /v1/comment-automations` — List comment-to-DM automations
- `POST /v1/comment-automations` — Create comment-to-DM automation
- `DELETE /v1/comment-automations/{automationId}` — Delete automation
- `GET /v1/comment-automations/{automationId}` — Get automation details
- `PATCH /v1/comment-automations/{automationId}` — Update automation settings
- `GET /v1/comment-automations/{automationId}/logs` — List automation logs

## Comments (15)

- `GET /v1/inbox/comments` — List commented posts
- `DELETE /v1/inbox/comments/{postId}` — Delete comment
- `GET /v1/inbox/comments/{postId}` — Get post comments
- `POST /v1/inbox/comments/{postId}` — Reply to comment
- `PATCH /v1/inbox/comments/{postId}/{commentId}` — Edit comment
- `DELETE /v1/inbox/comments/{postId}/{commentId}/hide` — Unhide comment
- `POST /v1/inbox/comments/{postId}/{commentId}/hide` — Hide comment
- `DELETE /v1/inbox/comments/{postId}/{commentId}/like` — Unlike comment
- `POST /v1/inbox/comments/{postId}/{commentId}/like` — Like comment
- `POST /v1/inbox/comments/{postId}/{commentId}/moderation` — Set comment moderation status
- `DELETE /v1/inbox/comments/{postId}/{commentId}/pin` — Unpin comment
- `POST /v1/inbox/comments/{postId}/{commentId}/pin` — Pin comment
- `POST /v1/inbox/comments/{postId}/{commentId}/private-reply` — Send private reply
- `DELETE /v1/inbox/posts/{postId}/like` — Unlike post
- `POST /v1/inbox/posts/{postId}/like` — Like post

## Connect (54)

- `GET /v1/accounts/{accountId}/facebook-page` — List Facebook pages
- `PUT /v1/accounts/{accountId}/facebook-page` — Update Facebook page
- `GET /v1/accounts/{accountId}/gmb-locations` — List Google Business Profile locations
- `PUT /v1/accounts/{accountId}/gmb-locations` — Update Google Business Profile location
- `POST /v1/accounts/{accountId}/gmb-locations/assign` — Assign Google Business Profile location to another profile
- `PUT /v1/accounts/{accountId}/linkedin-organization` — Switch LinkedIn account type
- `GET /v1/accounts/{accountId}/linkedin-organizations` — List LinkedIn orgs
- `GET /v1/accounts/{accountId}/pinterest-boards` — List Pinterest boards
- `POST /v1/accounts/{accountId}/pinterest-boards` — Create Pinterest board
- `PUT /v1/accounts/{accountId}/pinterest-boards` — Set default Pinterest board
- `GET /v1/accounts/{accountId}/reddit-flairs` — List subreddit flairs
- `POST /v1/accounts/{accountId}/reddit-flairs` — Set Reddit post flair
- `GET /v1/accounts/{accountId}/reddit-subreddits` — List Reddit subreddits
- `PUT /v1/accounts/{accountId}/reddit-subreddits` — Set default subreddit
- `GET /v1/accounts/{accountId}/reddit-subreddits/{subreddit}/rules` — Get subreddit rules
- `POST /v1/accounts/{accountId}/reddit-vote` — Vote on a Reddit post or comment
- `GET /v1/accounts/{accountId}/webhook-subscription` — Read a Facebook Page's webhook subscription
- `POST /v1/accounts/{accountId}/webhook-subscription` — Re-subscribe a Facebook Page to Zernio's webhooks
- `GET /v1/accounts/{accountId}/youtube-captions` — Get a YouTube video transcript
- `GET /v1/accounts/{accountId}/youtube-playlists` — List YouTube playlists
- `PUT /v1/accounts/{accountId}/youtube-playlists` — Set default YouTube playlist
- `POST /v1/connect/bluesky/credentials` — Connect Bluesky account
- `POST /v1/connect/discord` — Connect a Discord channel
- `GET /v1/connect/facebook/select-page` — List Facebook pages
- `POST /v1/connect/facebook/select-page` — Select Facebook page
- `GET /v1/connect/googlebusiness/locations` — List Google Business Profile locations
- `POST /v1/connect/googlebusiness/select-location` — Select Google Business Profile location
- `GET /v1/connect/instagram/select-account` — List Pages with a linked Instagram account
- `POST /v1/connect/instagram/select-account` — Select the Page whose Instagram account to connect
- `GET /v1/connect/linkedin/organizations` — List LinkedIn orgs
- `POST /v1/connect/linkedin/select-organization` — Select LinkedIn org
- `GET /v1/connect/meta-ads/callback` — Complete Meta business login
- `POST /v1/connect/openai-ads/credentials` — Connect an OpenAI Ads account
- `GET /v1/connect/pending-data` — Get pending OAuth data
- `GET /v1/connect/pinterest/select-board` — List Pinterest boards
- `POST /v1/connect/pinterest/select-board` — Select Pinterest board
- `GET /v1/connect/shopify` — Get Shopify OAuth connect URL
- `POST /v1/connect/shopify/token` — Connect a Shopify store with a custom-app Admin token
- `GET /v1/connect/slack` — List Slack channels for the channel picker
- `POST /v1/connect/slack` — Connect a Slack channel
- `GET /v1/connect/snapchat/select-profile` — List Snapchat profiles
- `POST /v1/connect/snapchat/select-profile` — Select Snapchat profile
- `GET /v1/connect/telegram` — Generate Telegram code
- `PATCH /v1/connect/telegram` — Check Telegram status
- `POST /v1/connect/telegram` — Connect Telegram directly
- `PATCH /v1/connect/tiktok-ads` — Set TikTok brand identity
- `POST /v1/connect/whatsapp/credentials` — Connect WhatsApp via credentials
- `POST /v1/connect/whatsapp/embedded-signup` — Connect WhatsApp from Embedded Signup
- `GET /v1/connect/whatsapp/sdk-config` — Get Embedded Signup SDK config
- `GET /v1/connect/whatsapp/select-phone-number` — List numbers for selection
- `POST /v1/connect/whatsapp/select-phone-number` — Complete number selection
- `GET /v1/connect/{platform}` — Get OAuth connect URL
- `POST /v1/connect/{platform}` — Complete OAuth callback
- `GET /v1/connect/{platform}/ads` — Connect ads for a platform

## Connected Apps (2)

- `GET /v1/me/connected-apps` — List connected apps
- `DELETE /v1/me/connected-apps/{clientId}` — Revoke connected app

## Contacts (7)

- `GET /v1/contacts` — List contacts
- `POST /v1/contacts` — Create contact
- `POST /v1/contacts/bulk` — Bulk create contacts
- `DELETE /v1/contacts/{contactId}` — Delete contact
- `GET /v1/contacts/{contactId}` — Get contact
- `PATCH /v1/contacts/{contactId}` — Update contact
- `GET /v1/contacts/{contactId}/channels` — List channels for a contact

## Conversions (14)

- `GET /v1/accounts/{accountId}/conversion-destinations` — List conversion destinations
- `POST /v1/accounts/{accountId}/conversion-destinations` — Create a conversion destination
- `DELETE /v1/accounts/{accountId}/conversion-destinations/{destinationId}` — Delete a conversion destination
- `GET /v1/accounts/{accountId}/conversion-destinations/{destinationId}` — Get a conversion destination
- `PATCH /v1/accounts/{accountId}/conversion-destinations/{destinationId}` — Update a conversion destination
- `DELETE /v1/accounts/{accountId}/conversion-destinations/{destinationId}/associations` — Remove associated campaigns
- `GET /v1/accounts/{accountId}/conversion-destinations/{destinationId}/associations` — List associated campaigns
- `POST /v1/accounts/{accountId}/conversion-destinations/{destinationId}/associations` — Associate campaigns
- `GET /v1/accounts/{accountId}/conversion-destinations/{destinationId}/metrics` — Get attribution metrics
- `POST /v1/ads/conversions` — Send conversion events
- `GET /v1/ads/conversions/actions` — List conversion actions
- `POST /v1/ads/conversions/actions` — Create website conversion action
- `POST /v1/ads/conversions/adjustments` — Adjust uploaded conversions
- `GET /v1/ads/conversions/quality` — Get Event Match Quality

## Custom Fields (6)

- `DELETE /v1/contacts/{contactId}/fields/{slug}` — Clear custom field value
- `PUT /v1/contacts/{contactId}/fields/{slug}` — Set custom field value
- `GET /v1/custom-fields` — List custom field definitions
- `POST /v1/custom-fields` — Create custom field
- `DELETE /v1/custom-fields/{fieldId}` — Delete custom field
- `PATCH /v1/custom-fields/{fieldId}` — Update custom field

## Discord (24)

- `GET /v1/accounts/{accountId}/discord-channels` — List Discord guild channels
- `GET /v1/accounts/{accountId}/discord-settings` — Get Discord account settings
- `PATCH /v1/accounts/{accountId}/discord-settings` — Update Discord settings
- `DELETE /v1/discord/channels/{channelId}/messages/{messageId}` — Delete a Discord channel message
- `POST /v1/discord/channels/{channelId}/messages/{messageId}/crosspost` — Crosspost Discord message
- `GET /v1/discord/channels/{channelId}/pins` — List pinned messages
- `DELETE /v1/discord/channels/{channelId}/pins/{messageId}` — Unpin a Discord message
- `PUT /v1/discord/channels/{channelId}/pins/{messageId}` — Pin a Discord message
- `POST /v1/discord/channels/{channelId}/threads` — Create a Discord public thread
- `POST /v1/discord/dms` — Send a Discord Direct Message
- `GET /v1/discord/guilds/{guildId}/events` — List Discord scheduled events
- `POST /v1/discord/guilds/{guildId}/events` — Create a Discord scheduled event
- `DELETE /v1/discord/guilds/{guildId}/events/{eventId}` — Delete a Discord scheduled event
- `GET /v1/discord/guilds/{guildId}/events/{eventId}` — Get a Discord scheduled event
- `PATCH /v1/discord/guilds/{guildId}/events/{eventId}` — Update a Discord scheduled event
- `GET /v1/discord/guilds/{guildId}/members` — List Discord guild members
- `GET /v1/discord/guilds/{guildId}/members/search` — Search Discord guild members
- `GET /v1/discord/guilds/{guildId}/members/{userId}` — Get a Discord guild member
- `DELETE /v1/discord/guilds/{guildId}/members/{userId}/roles/{roleId}` — Remove a role from a guild member
- `PUT /v1/discord/guilds/{guildId}/members/{userId}/roles/{roleId}` — Assign a role to a guild member
- `GET /v1/discord/guilds/{guildId}/roles` — List Discord guild roles
- `POST /v1/discord/guilds/{guildId}/roles` — Create a Discord guild role
- `DELETE /v1/discord/guilds/{guildId}/roles/{roleId}` — Delete a Discord guild role
- `PATCH /v1/discord/guilds/{guildId}/roles/{roleId}` — Edit a Discord guild role

## GMB Attributes (3)

- `GET /v1/accounts/{accountId}/gmb-attribute-metadata` — Get attribute metadata
- `GET /v1/accounts/{accountId}/gmb-attributes` — Get attributes
- `PUT /v1/accounts/{accountId}/gmb-attributes` — Update attributes

## GMB Food Menus (2)

- `GET /v1/accounts/{accountId}/gmb-food-menus` — Get food menus
- `PUT /v1/accounts/{accountId}/gmb-food-menus` — Update food menus

## GMB Location Details (2)

- `GET /v1/accounts/{accountId}/gmb-location-details` — Get location details
- `PUT /v1/accounts/{accountId}/gmb-location-details` — Update location details

## GMB Media (3)

- `DELETE /v1/accounts/{accountId}/gmb-media` — Delete photo
- `GET /v1/accounts/{accountId}/gmb-media` — List media
- `POST /v1/accounts/{accountId}/gmb-media` — Upload photo

## GMB Place Actions (4)

- `DELETE /v1/accounts/{accountId}/gmb-place-actions` — Delete action link
- `GET /v1/accounts/{accountId}/gmb-place-actions` — List action links
- `PATCH /v1/accounts/{accountId}/gmb-place-actions` — Update action link
- `POST /v1/accounts/{accountId}/gmb-place-actions` — Create action link

## GMB Reviews (5)

- `GET /v1/accounts/{accountId}/gmb-reviews` — Get reviews
- `POST /v1/accounts/{accountId}/gmb-reviews/batch` — Batch get reviews
- `GET /v1/accounts/{accountId}/gmb-reviews/{reviewId}` — Get a review
- `DELETE /v1/accounts/{accountId}/gmb-reviews/{reviewId}/reply` — Delete a review reply
- `POST /v1/accounts/{accountId}/gmb-reviews/{reviewId}/reply` — Reply to a review

## GMB Services (2)

- `GET /v1/accounts/{accountId}/gmb-services` — Get services
- `PUT /v1/accounts/{accountId}/gmb-services` — Replace services

## GMB Verifications (4)

- `GET /v1/accounts/{accountId}/gmb-verifications` — Get verification state
- `POST /v1/accounts/{accountId}/gmb-verifications` — Start a verification
- `POST /v1/accounts/{accountId}/gmb-verifications/options` — Fetch verification options
- `POST /v1/accounts/{accountId}/gmb-verifications/{verificationId}/complete` — Complete a verification

## Inbox Analytics (7)

- `GET /v1/analytics/inbox/conversations` — List conversation analytics
- `GET /v1/analytics/inbox/conversations/{conversationId}` — Get conversation analytics
- `GET /v1/analytics/inbox/heatmap` — Get day × hour heatmap
- `GET /v1/analytics/inbox/response-time` — Get inbox response-time stats
- `GET /v1/analytics/inbox/source-breakdown` — Get inbox source breakdown
- `GET /v1/analytics/inbox/top-accounts` — Get top accounts by inbox volume
- `GET /v1/analytics/inbox/volume` — Get inbox messaging volume

## Instagram (5)

- `GET /v1/accounts/{accountId}/instagram/audio` — Search Instagram audio
- `GET /v1/accounts/{accountId}/instagram/audio/{audioId}` — Get Instagram audio metadata
- `GET /v1/accounts/{accountId}/instagram/publishing-limit` — Get Instagram publishing limit
- `GET /v1/accounts/{accountId}/instagram/stories` — List active Instagram stories
- `GET /v1/accounts/{accountId}/instagram/stories/{storyId}/insights` — Get Instagram story insights

## Invites (1)

- `POST /v1/invite/tokens` — Create invite token

## Lead Gen (7)

- `GET /v1/ads/lead-forms` — List lead forms
- `POST /v1/ads/lead-forms` — Create a lead form
- `DELETE /v1/ads/lead-forms/{formId}` — Archive a lead form
- `GET /v1/ads/lead-forms/{formId}` — Get a lead form
- `GET /v1/ads/lead-forms/{formId}/leads` — List leads for a single form
- `POST /v1/ads/lead-forms/{formId}/test-leads` — Create a test lead
- `GET /v1/ads/leads` — List submitted leads

## LinkedIn Mentions (1)

- `GET /v1/accounts/{accountId}/linkedin-mentions` — Resolve LinkedIn mention

## Logs (1)

- `GET /v1/logs` — List activity logs

## Media (1)

- `POST /v1/media/presign` — Get upload URL

## Mentions (2)

- `GET /v1/inbox/mentions` — List mentions
- `POST /v1/inbox/mentions/reply` — Reply to a mention

## Messages (16)

- `GET /v1/inbox/conversations` — List conversations
- `POST /v1/inbox/conversations` — Create conversation
- `GET /v1/inbox/conversations/search` — Search conversations
- `GET /v1/inbox/conversations/{conversationId}` — Get conversation
- `PUT /v1/inbox/conversations/{conversationId}` — Update conversation status
- `GET /v1/inbox/conversations/{conversationId}/messages` — List messages
- `POST /v1/inbox/conversations/{conversationId}/messages` — Send message
- `DELETE /v1/inbox/conversations/{conversationId}/messages/{messageId}` — Delete message
- `PATCH /v1/inbox/conversations/{conversationId}/messages/{messageId}` — Edit message
- `GET /v1/inbox/conversations/{conversationId}/messages/{messageId}/attachments/{index}` — Resolve message attachment
- `DELETE /v1/inbox/conversations/{conversationId}/messages/{messageId}/reactions` — Remove reaction
- `POST /v1/inbox/conversations/{conversationId}/messages/{messageId}/reactions` — Add reaction
- `POST /v1/inbox/conversations/{conversationId}/read` — Mark a conversation as read
- `POST /v1/inbox/conversations/{conversationId}/thread-control` — Hand a conversation to or from Meta Business Agent
- `POST /v1/inbox/conversations/{conversationId}/typing` — Send typing indicator
- `POST /v1/media/upload-direct` — Upload media file

## Messaging Ads (3)

- `POST /v1/ads/call` — Create Click-to-Call ad
- `POST /v1/ads/ctwa` — Create CTWA ad (deprecated) **(deprecated)**
- `POST /v1/ads/messaging` — Create messaging ad

## Phone Numbers (28)

- `GET /v1/phone-numbers` — List phone numbers
- `GET /v1/phone-numbers/availability` — Check country availability
- `GET /v1/phone-numbers/available` — Search available numbers
- `GET /v1/phone-numbers/countries` — List offerable number countries
- `GET /v1/phone-numbers/kyc` — Get KYC form spec
- `POST /v1/phone-numbers/kyc` — Submit KYC
- `GET /v1/phone-numbers/kyc/document/{documentId}` — View a KYC document on file
- `POST /v1/phone-numbers/kyc/review-packet` — Pre-review a KYC packet
- `POST /v1/phone-numbers/kyc/share` — Create a hosted KYC link
- `POST /v1/phone-numbers/kyc/upload-document` — Upload a KYC document
- `POST /v1/phone-numbers/kyc/validate-address` — Pre-validate KYC address
- `GET /v1/phone-numbers/port-in` — List port-in orders
- `POST /v1/phone-numbers/port-in` — Port numbers in
- `POST /v1/phone-numbers/port-in/check` — Check portability
- `POST /v1/phone-numbers/port-in/documents` — Upload a porting document
- `GET /v1/phone-numbers/port-in/requirements` — Country porting requirements
- `DELETE /v1/phone-numbers/port-in/{id}` — Cancel a port-in
- `GET /v1/phone-numbers/port-in/{id}/requirements` — A port-in order's pending requirements
- `POST /v1/phone-numbers/purchase` — Purchase phone number
- `GET /v1/phone-numbers/stock-watches` — List stock watches
- `POST /v1/phone-numbers/stock-watches` — Watch an out-of-stock country
- `DELETE /v1/phone-numbers/stock-watches/{id}` — Stop watching a country
- `DELETE /v1/phone-numbers/{id}` — Release phone number
- `GET /v1/phone-numbers/{id}` — Get phone number
- `GET /v1/phone-numbers/{id}/remediate` — Get declined requirements
- `POST /v1/phone-numbers/{id}/remediate` — Resubmit a declined number
- `POST /v1/phone-numbers/{id}/remediate/reply` — Reply to the regulatory reviewer
- `POST /v1/phone-numbers/{id}/remediate/respond` — Respond to the regulatory reviewer (message + corrections)

## Posts (10)

- `GET /v1/posts` — List posts
- `POST /v1/posts` — Create post
- `POST /v1/posts/bulk-upload` — Bulk upload from CSV
- `DELETE /v1/posts/{postId}` — Delete post
- `GET /v1/posts/{postId}` — Get post
- `PUT /v1/posts/{postId}` — Update post
- `POST /v1/posts/{postId}/edit` — Edit published post
- `POST /v1/posts/{postId}/retry` — Retry failed post
- `POST /v1/posts/{postId}/unpublish` — Unpublish post
- `POST /v1/posts/{postId}/update-metadata` — Update post metadata

## Profiles (5)

- `GET /v1/profiles` — List profiles
- `POST /v1/profiles` — Create profile
- `DELETE /v1/profiles/{profileId}` — Delete profile
- `GET /v1/profiles/{profileId}` — Get profile
- `PUT /v1/profiles/{profileId}` — Update profile

## Queue (6)

- `GET /v1/queue/next-slot` — Get next available slot
- `GET /v1/queue/preview` — Preview upcoming slots
- `DELETE /v1/queue/slots` — Delete schedule
- `GET /v1/queue/slots` — List schedules
- `POST /v1/queue/slots` — Create schedule
- `PUT /v1/queue/slots` — Update schedule

## Reach and Frequency (4)

- `POST /v1/ads/rf-predictions` — Create reach-frequency prediction
- `DELETE /v1/ads/rf-predictions/{predictionId}` — Cancel reach-frequency booking
- `GET /v1/ads/rf-predictions/{predictionId}` — Get reach-frequency prediction
- `POST /v1/ads/rf-predictions/{predictionId}/reserve` — Reserve reach-frequency inventory

## Reddit Search (2)

- `GET /v1/reddit/feed` — Get subreddit feed
- `GET /v1/reddit/search` — Search posts

## Reviews (3)

- `GET /v1/inbox/reviews` — List reviews
- `DELETE /v1/inbox/reviews/{reviewId}/reply` — Delete review reply
- `POST /v1/inbox/reviews/{reviewId}/reply` — Reply to review

## SMS (22)

- `DELETE /v1/phone-numbers/{id}/sms` — Disable SMS on a number
- `POST /v1/phone-numbers/{id}/sms` — Enable SMS on a number
- `POST /v1/phone-numbers/{id}/sms/reuse-registration` — Add number to SMS registration
- `GET /v1/sms/lookup` — Look up carrier + line type
- `POST /v1/sms/messages` — Send an SMS/MMS
- `POST /v1/sms/opt-in-proof` — Upload opt-in form proof
- `GET /v1/sms/opt-outs` — List SMS opt-outs
- `GET /v1/sms/registrations` — List carrier registrations
- `POST /v1/sms/registrations` — Start a carrier registration
- `POST /v1/sms/registrations/preflight` — Pre-check a carrier registration
- `POST /v1/sms/registrations/share` — Create a registration share link
- `DELETE /v1/sms/registrations/{id}` — Deactivate a brand/campaign registration
- `GET /v1/sms/registrations/{id}` — Get a carrier registration
- `POST /v1/sms/registrations/{id}/appeal` — Appeal a rejected campaign
- `POST /v1/sms/registrations/{id}/opt-in-proof` — Upload opt-in form proof for an appeal
- `POST /v1/sms/registrations/{id}/resend-otp` — Re-send the sole-prop OTP
- `POST /v1/sms/registrations/{id}/respond` — Reply to a change request
- `POST /v1/sms/registrations/{id}/verify-otp` — Submit the sole-prop OTP
- `GET /v1/sms/sender-ids` — List alphanumeric sender IDs
- `POST /v1/sms/sender-ids` — Create an alphanumeric sender ID
- `POST /v1/sms/sender-ids/limit-request` — Request a higher sender ID daily limit
- `DELETE /v1/sms/sender-ids/{id}` — Delete an alphanumeric sender ID

## Sequences (10)

- `GET /v1/sequences` — List sequences
- `POST /v1/sequences` — Create sequence
- `DELETE /v1/sequences/{sequenceId}` — Delete sequence
- `GET /v1/sequences/{sequenceId}` — Get sequence with steps
- `PATCH /v1/sequences/{sequenceId}` — Update sequence
- `POST /v1/sequences/{sequenceId}/activate` — Activate sequence
- `POST /v1/sequences/{sequenceId}/enroll` — Enroll contacts in a sequence
- `DELETE /v1/sequences/{sequenceId}/enroll/{contactId}` — Unenroll contact
- `GET /v1/sequences/{sequenceId}/enrollments` — List enrollments for a sequence
- `POST /v1/sequences/{sequenceId}/pause` — Pause sequence

## Slack (1)

- `GET /v1/accounts/{accountId}/slack-members` — List Slack workspace members

## Tools (1)

- `GET /v1/tools/tiktok/download` — Download a TikTok video

## Tracking Tags (10)

- `GET /v1/accounts/{accountId}/tracking-tags` — List tracking tags
- `POST /v1/accounts/{accountId}/tracking-tags` — Create a tracking tag
- `GET /v1/accounts/{accountId}/tracking-tags/{tagId}` — Get a tracking tag
- `PATCH /v1/accounts/{accountId}/tracking-tags/{tagId}` — Update a tracking tag
- `DELETE /v1/accounts/{accountId}/tracking-tags/{tagId}/shared-accounts` — Stop sharing with an account
- `GET /v1/accounts/{accountId}/tracking-tags/{tagId}/shared-accounts` — List accounts it is shared with
- `POST /v1/accounts/{accountId}/tracking-tags/{tagId}/shared-accounts` — Share with an ad account
- `GET /v1/accounts/{accountId}/tracking-tags/{tagId}/stats` — Get aggregated event stats
- `GET /v1/ads/{adId}/tracking-tags` — Get ad tracking tags
- `PATCH /v1/ads/{adId}/tracking-tags` — Set ad tracking tags

## Twitter Engagement (8)

- `DELETE /v1/twitter/bookmark` — Remove bookmark
- `POST /v1/twitter/bookmark` — Bookmark a tweet
- `DELETE /v1/twitter/follow` — Unfollow a user
- `POST /v1/twitter/follow` — Follow a user
- `DELETE /v1/twitter/retweet` — Undo retweet
- `POST /v1/twitter/retweet` — Retweet a post
- `GET /v1/twitter/search` — Search recent tweets
- `GET /v1/twitter/tweet` — Look up a tweet

## Usage (6)

- `GET /v1/billing` — Account billing snapshot (plan, cycle, balance, caps, status)
- `GET /v1/billing/x-pricing` — Get X API pricing table
- `GET /v1/usage` — Usage snapshot (default) or billed-spend metering (with params)
- `GET /v1/usage-stats` — Get plan and usage snapshot (plan, limits, payment status) **(deprecated)**
- `GET /v1/usage/calls` — Calling usage and cost
- `GET /v1/usage/sms` — SMS usage (volumes)

## Users (2)

- `GET /v1/users` — List users
- `GET /v1/users/{userId}` — Get user

## Validate (4)

- `POST /v1/tools/validate/media` — Validate media URL
- `POST /v1/tools/validate/post` — Validate post content
- `POST /v1/tools/validate/post-length` — Validate character count
- `GET /v1/tools/validate/subreddit` — Check subreddit existence

## Verify (3)

- `POST /v1/verify/verifications` — Send a verification code
- `GET /v1/verify/verifications/{verificationId}` — Get a verification
- `POST /v1/verify/verifications/{verificationId}/check` — Check a verification code

## Voice (18)

- `GET /v1/phone-numbers/sip-trunks` — List SIP trunks
- `POST /v1/phone-numbers/sip-trunks` — Create a SIP trunk
- `DELETE /v1/phone-numbers/sip-trunks/{id}` — Delete a SIP trunk
- `GET /v1/phone-numbers/sip-trunks/{id}` — Get a SIP trunk
- `POST /v1/phone-numbers/sip-trunks/{id}/rotate-credentials` — Rotate a SIP trunk's password
- `DELETE /v1/phone-numbers/{id}/sip-trunk` — Detach a number from its SIP trunk
- `POST /v1/phone-numbers/{id}/sip-trunk` — Attach a number to a SIP trunk
- `DELETE /v1/phone-numbers/{id}/voice` — Disable phone calling on a number
- `POST /v1/phone-numbers/{id}/voice` — Enable phone calling on a number
- `GET /v1/voice/calls` — List phone calls
- `POST /v1/voice/calls` — Place an outbound phone call
- `GET /v1/voice/calls/estimate` — Estimate call cost
- `POST /v1/voice/calls/web` — Mint a browser softphone session
- `POST /v1/voice/calls/web/dial` — Dial from the browser softphone
- `GET /v1/voice/calls/{id}` — Get a phone call
- `POST /v1/voice/calls/{id}/end` — Hang up a live call
- `GET /v1/voice/calls/{id}/recording` — Get a call recording
- `POST /v1/voice/calls/{id}/transfer` — Blind-transfer a live call

## Webhooks (7)

- `GET /v1/webhooks/logs` — List webhook delivery logs
- `POST /v1/webhooks/logs/redeliver` — Redeliver a webhook event
- `DELETE /v1/webhooks/settings` — Delete webhook
- `GET /v1/webhooks/settings` — List webhooks
- `POST /v1/webhooks/settings` — Create webhook
- `PUT /v1/webhooks/settings` — Update webhook
- `POST /v1/webhooks/test` — Send test webhook

## WhatsApp (41)

- `POST /v1/accounts/{accountId}/whatsapp/register` — Register a connected WhatsApp number on the Cloud API
- `POST /v1/accounts/{accountId}/whatsapp/request-code` — Request a Meta re-verification code for a BYO WhatsApp number
- `POST /v1/accounts/{accountId}/whatsapp/verify-code` — Verify the Meta re-verification code for a BYO WhatsApp number
- `GET /v1/whatsapp/account-events` — List account notifications
- `DELETE /v1/whatsapp/block-users` — Unblock users
- `GET /v1/whatsapp/block-users` — List blocked users
- `POST /v1/whatsapp/block-users` — Block users
- `GET /v1/whatsapp/block-users/status` — Check if a user is blocked
- `GET /v1/whatsapp/business-profile` — Get business profile
- `POST /v1/whatsapp/business-profile` — Update business profile
- `GET /v1/whatsapp/business-profile/display-name` — Get display name status
- `POST /v1/whatsapp/business-profile/display-name` — Request display name change
- `POST /v1/whatsapp/business-profile/photo` — Upload profile picture
- `DELETE /v1/whatsapp/business-profile/username` — Delete business username
- `GET /v1/whatsapp/business-profile/username` — Get business username
- `POST /v1/whatsapp/business-profile/username` — Set business username
- `GET /v1/whatsapp/business-profile/username/suggestions` — Get username suggestions
- `GET /v1/whatsapp/conversions` — List conversion events
- `POST /v1/whatsapp/conversions` — Send WhatsApp conversion event
- `GET /v1/whatsapp/dataset` — Get CTWA conversions dataset
- `POST /v1/whatsapp/dataset` — Provision CTWA dataset
- `GET /v1/whatsapp/media/{mediaId}` — Download WhatsApp media
- `GET /v1/whatsapp/templates` — List templates
- `POST /v1/whatsapp/templates` — Create template
- `DELETE /v1/whatsapp/templates/id/{templateId}` — Delete template by id
- `GET /v1/whatsapp/templates/id/{templateId}` — Get template by id
- `PATCH /v1/whatsapp/templates/id/{templateId}` — Update template by id
- `DELETE /v1/whatsapp/templates/{templateName}` — Delete template
- `GET /v1/whatsapp/templates/{templateName}` — Get template
- `PATCH /v1/whatsapp/templates/{templateName}` — Update template
- `GET /v1/whatsapp/wa-groups` — List active groups
- `POST /v1/whatsapp/wa-groups` — Create group
- `DELETE /v1/whatsapp/wa-groups/{groupId}` — Delete group
- `GET /v1/whatsapp/wa-groups/{groupId}` — Get group info
- `POST /v1/whatsapp/wa-groups/{groupId}` — Update group settings
- `POST /v1/whatsapp/wa-groups/{groupId}/invite-link` — Create invite link
- `DELETE /v1/whatsapp/wa-groups/{groupId}/join-requests` — Reject join requests
- `GET /v1/whatsapp/wa-groups/{groupId}/join-requests` — List join requests
- `POST /v1/whatsapp/wa-groups/{groupId}/join-requests` — Approve join requests
- `DELETE /v1/whatsapp/wa-groups/{groupId}/participants` — Remove participants
- `POST /v1/whatsapp/wa-groups/{groupId}/participants` — Add participants

## WhatsApp Calling (16)

- `POST /v1/phone-numbers/{id}/whatsapp/caller-id-verification` — Start caller-ID verification for a customer-brought number
- `POST /v1/phone-numbers/{id}/whatsapp/caller-id-verification/verify` — Confirm the caller-ID verification code
- `DELETE /v1/phone-numbers/{id}/whatsapp/calling` — Disable calling on a number
- `GET /v1/phone-numbers/{id}/whatsapp/calling` — Get calling config for a number
- `PATCH /v1/phone-numbers/{id}/whatsapp/calling` — Update calling config
- `POST /v1/phone-numbers/{id}/whatsapp/calling` — Enable calling on a number
- `GET /v1/whatsapp/call-permissions` — Check call permission
- `GET /v1/whatsapp/calling` — Get calling config for an account
- `GET /v1/whatsapp/calls` — List call history for an account
- `POST /v1/whatsapp/calls` — Initiate outbound call
- `GET /v1/whatsapp/calls/estimate` — Estimate per-minute cost
- `GET /v1/whatsapp/calls/{id}` — Get a single call
- `GET /v1/whatsapp/calls/{id}/recording` — Get a call recording
- `DELETE /v1/whatsapp/phone-numbers/{id}/calling` — Disable calling on a number **(deprecated)**
- `PATCH /v1/whatsapp/phone-numbers/{id}/calling` — Update calling config **(deprecated)**
- `POST /v1/whatsapp/phone-numbers/{id}/calling` — Enable calling on a number **(deprecated)**

## WhatsApp Flows (15)

- `GET /v1/whatsapp/flow-responses` — List flow responses
- `GET /v1/whatsapp/flows` — List flows
- `POST /v1/whatsapp/flows` — Create flow
- `GET /v1/whatsapp/flows/encryption-key` — Get Flows encryption key status
- `POST /v1/whatsapp/flows/encryption-key` — Register a Flows encryption key
- `POST /v1/whatsapp/flows/send` — Send flow message
- `DELETE /v1/whatsapp/flows/{flowId}` — Delete flow
- `GET /v1/whatsapp/flows/{flowId}` — Get flow
- `PATCH /v1/whatsapp/flows/{flowId}` — Update flow
- `POST /v1/whatsapp/flows/{flowId}/deprecate` — Deprecate flow
- `GET /v1/whatsapp/flows/{flowId}/json` — Get flow JSON asset
- `PUT /v1/whatsapp/flows/{flowId}/json` — Upload flow JSON
- `GET /v1/whatsapp/flows/{flowId}/preview` — Get flow preview URL
- `POST /v1/whatsapp/flows/{flowId}/publish` — Publish flow
- `GET /v1/whatsapp/flows/{flowId}/versions` — List flow versions

## WhatsApp Phone Numbers (16)

- `GET /v1/whatsapp/number-info` — Get number status
- `GET /v1/whatsapp/phone-numbers` — List phone numbers **(deprecated)**
- `GET /v1/whatsapp/phone-numbers/availability` — Check country availability **(deprecated)**
- `GET /v1/whatsapp/phone-numbers/available` — Search available numbers **(deprecated)**
- `GET /v1/whatsapp/phone-numbers/countries` — List offerable number countries **(deprecated)**
- `GET /v1/whatsapp/phone-numbers/kyc` — Get KYC form spec **(deprecated)**
- `POST /v1/whatsapp/phone-numbers/kyc` — Submit KYC **(deprecated)**
- `POST /v1/whatsapp/phone-numbers/kyc/share` — Create a hosted KYC link **(deprecated)**
- `POST /v1/whatsapp/phone-numbers/kyc/upload-document` — Upload a KYC document **(deprecated)**
- `POST /v1/whatsapp/phone-numbers/kyc/validate-address` — Pre-validate KYC address **(deprecated)**
- `POST /v1/whatsapp/phone-numbers/purchase` — Purchase phone number **(deprecated)**
- `PATCH /v1/whatsapp/phone-numbers/{id}/profile` — Move a number to another profile
- `GET /v1/whatsapp/phone-numbers/{id}/remediate` — Get declined requirements **(deprecated)**
- `POST /v1/whatsapp/phone-numbers/{id}/remediate` — Resubmit a declined number **(deprecated)**
- `DELETE /v1/whatsapp/phone-numbers/{phoneNumberId}` — Release phone number **(deprecated)**
- `GET /v1/whatsapp/phone-numbers/{phoneNumberId}` — Get phone number **(deprecated)**

## WhatsApp Sandbox (3)

- `GET /v1/whatsapp/sandbox/sessions` — List your sandbox sessions
- `POST /v1/whatsapp/sandbox/sessions` — Start a sandbox activation
- `DELETE /v1/whatsapp/sandbox/sessions/{sessionId}` — Revoke a sandbox session

## WhatsApp Templates (1)

- `GET /v1/whatsapp/template-library` — Look up a library template

## Workflows (14)

- `GET /v1/workflows` — List workflows
- `POST /v1/workflows` — Create workflow
- `DELETE /v1/workflows/{workflowId}` — Delete workflow
- `GET /v1/workflows/{workflowId}` — Get workflow with graph
- `PATCH /v1/workflows/{workflowId}` — Update workflow
- `POST /v1/workflows/{workflowId}/activate` — Activate workflow
- `POST /v1/workflows/{workflowId}/duplicate` — Duplicate a workflow
- `GET /v1/workflows/{workflowId}/executions` — List workflow runs
- `POST /v1/workflows/{workflowId}/executions` — Manually start a workflow run
- `GET /v1/workflows/{workflowId}/executions/{executionId}/events` — Get an execution's timeline
- `POST /v1/workflows/{workflowId}/pause` — Pause workflow
- `GET /v1/workflows/{workflowId}/versions` — List a workflow's version history
- `GET /v1/workflows/{workflowId}/versions/{version}` — Get a specific workflow version
- `POST /v1/workflows/{workflowId}/versions/{version}/restore` — Restore a workflow version
