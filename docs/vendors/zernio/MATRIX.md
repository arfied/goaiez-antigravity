# Zernio capability matrix — GMB / Facebook / Instagram / WhatsApp module

Generated 2026-09-12 from `openapi.json` (Zernio API 1.0.4). Left: the blueprint feature (boss's blueprint section). Right: the Zernio tags that serve it, with every operation under those tags. A feature whose column is thin here is a feature Zernio does not expose, and the lane must say so before building.


## §4 Reviews & webhooks: read/filter Google reviews; reply

Tags: GMB Reviews, Reviews, Webhooks


**GMB Reviews** (5)

- `GET /v1/accounts/{accountId}/gmb-reviews` — Get reviews
- `POST /v1/accounts/{accountId}/gmb-reviews/batch` — Batch get reviews
- `GET /v1/accounts/{accountId}/gmb-reviews/{reviewId}` — Get a review
- `POST /v1/accounts/{accountId}/gmb-reviews/{reviewId}/reply` — Reply to a review
- `DELETE /v1/accounts/{accountId}/gmb-reviews/{reviewId}/reply` — Delete a review reply

**Reviews** (3)

- `GET /v1/inbox/reviews` — List reviews
- `POST /v1/inbox/reviews/{reviewId}/reply` — Reply to review
- `DELETE /v1/inbox/reviews/{reviewId}/reply` — Delete review reply

**Webhooks** (7)

- `GET /v1/webhooks/settings` — List webhooks
- `POST /v1/webhooks/settings` — Create webhook
- `PUT /v1/webhooks/settings` — Update webhook
- `DELETE /v1/webhooks/settings` — Delete webhook
- `GET /v1/webhooks/logs` — List webhook delivery logs
- `POST /v1/webhooks/logs/redeliver` — Redeliver a webhook event
- `POST /v1/webhooks/test` — Send test webhook

## §4 Facebook Recommendations read/list/reply; 'Doesn't Recommend' → ticket

Tags: Reviews (facebook), Webhooks


**Reviews** (3)

- `GET /v1/inbox/reviews` — List reviews
- `POST /v1/inbox/reviews/{reviewId}/reply` — Reply to review
- `DELETE /v1/inbox/reviews/{reviewId}/reply` — Delete review reply

**Webhooks** (7)

- `GET /v1/webhooks/settings` — List webhooks
- `POST /v1/webhooks/settings` — Create webhook
- `PUT /v1/webhooks/settings` — Update webhook
- `DELETE /v1/webhooks/settings` — Delete webhook
- `GET /v1/webhooks/logs` — List webhook delivery logs
- `POST /v1/webhooks/logs/redeliver` — Redeliver a webhook event
- `POST /v1/webhooks/test` — Send test webhook

## §3/§4 Omnichannel inbox: Messenger, Instagram DMs, WhatsApp

Tags: Messages, WhatsApp, Instagram, Inbox Analytics


**Messages** (16)

- `GET /v1/inbox/conversations` — List conversations
- `POST /v1/inbox/conversations` — Create conversation
- `GET /v1/inbox/conversations/search` — Search conversations
- `GET /v1/inbox/conversations/{conversationId}` — Get conversation
- `PUT /v1/inbox/conversations/{conversationId}` — Update conversation status
- `GET /v1/inbox/conversations/{conversationId}/messages` — List messages
- `POST /v1/inbox/conversations/{conversationId}/messages` — Send message
- `PATCH /v1/inbox/conversations/{conversationId}/messages/{messageId}` — Edit message
- `DELETE /v1/inbox/conversations/{conversationId}/messages/{messageId}` — Delete message
- `POST /v1/inbox/conversations/{conversationId}/typing` — Send typing indicator
- `POST /v1/inbox/conversations/{conversationId}/thread-control` — Hand a conversation to or from Meta Business Agent
- `POST /v1/inbox/conversations/{conversationId}/read` — Mark a conversation as read
- `POST /v1/inbox/conversations/{conversationId}/messages/{messageId}/reactions` — Add reaction
- `DELETE /v1/inbox/conversations/{conversationId}/messages/{messageId}/reactions` — Remove reaction
- `POST /v1/media/upload-direct` — Upload media file
- `GET /v1/inbox/conversations/{conversationId}/messages/{messageId}/attachments/{index}` — Resolve message attachment

**WhatsApp** (41)

- `POST /v1/accounts/{accountId}/whatsapp/register` — Register a connected WhatsApp number on the Cloud API
- `POST /v1/accounts/{accountId}/whatsapp/request-code` — Request a Meta re-verification code for a BYO WhatsApp number
- `POST /v1/accounts/{accountId}/whatsapp/verify-code` — Verify the Meta re-verification code for a BYO WhatsApp number
- `GET /v1/whatsapp/media/{mediaId}` — Download WhatsApp media
- `GET /v1/whatsapp/templates` — List templates
- `POST /v1/whatsapp/templates` — Create template
- `GET /v1/whatsapp/templates/{templateName}` — Get template
- `PATCH /v1/whatsapp/templates/{templateName}` — Update template
- `DELETE /v1/whatsapp/templates/{templateName}` — Delete template
- `GET /v1/whatsapp/templates/id/{templateId}` — Get template by id
- `PATCH /v1/whatsapp/templates/id/{templateId}` — Update template by id
- `DELETE /v1/whatsapp/templates/id/{templateId}` — Delete template by id
- `GET /v1/whatsapp/business-profile` — Get business profile
- `POST /v1/whatsapp/business-profile` — Update business profile
- `POST /v1/whatsapp/business-profile/photo` — Upload profile picture
- `GET /v1/whatsapp/business-profile/display-name` — Get display name status
- `POST /v1/whatsapp/business-profile/display-name` — Request display name change
- `GET /v1/whatsapp/business-profile/username` — Get business username
- `POST /v1/whatsapp/business-profile/username` — Set business username
- `DELETE /v1/whatsapp/business-profile/username` — Delete business username
- `GET /v1/whatsapp/business-profile/username/suggestions` — Get username suggestions
- `GET /v1/whatsapp/block-users/status` — Check if a user is blocked
- `GET /v1/whatsapp/block-users` — List blocked users
- `POST /v1/whatsapp/block-users` — Block users
- `DELETE /v1/whatsapp/block-users` — Unblock users
- `GET /v1/whatsapp/account-events` — List account notifications
- `GET /v1/whatsapp/dataset` — Get CTWA conversions dataset
- `POST /v1/whatsapp/dataset` — Provision CTWA dataset
- `GET /v1/whatsapp/wa-groups` — List active groups
- `POST /v1/whatsapp/wa-groups` — Create group
- `GET /v1/whatsapp/wa-groups/{groupId}` — Get group info
- `POST /v1/whatsapp/wa-groups/{groupId}` — Update group settings
- `DELETE /v1/whatsapp/wa-groups/{groupId}` — Delete group
- `POST /v1/whatsapp/wa-groups/{groupId}/participants` — Add participants
- `DELETE /v1/whatsapp/wa-groups/{groupId}/participants` — Remove participants
- `POST /v1/whatsapp/wa-groups/{groupId}/invite-link` — Create invite link
- `GET /v1/whatsapp/wa-groups/{groupId}/join-requests` — List join requests
- `POST /v1/whatsapp/wa-groups/{groupId}/join-requests` — Approve join requests
- `DELETE /v1/whatsapp/wa-groups/{groupId}/join-requests` — Reject join requests
- `GET /v1/whatsapp/conversions` — List conversion events
- `POST /v1/whatsapp/conversions` — Send WhatsApp conversion event

**Instagram** (5)

- `GET /v1/accounts/{accountId}/instagram/stories` — List active Instagram stories
- `GET /v1/accounts/{accountId}/instagram/publishing-limit` — Get Instagram publishing limit
- `GET /v1/accounts/{accountId}/instagram/audio` — Search Instagram audio
- `GET /v1/accounts/{accountId}/instagram/audio/{audioId}` — Get Instagram audio metadata
- `GET /v1/accounts/{accountId}/instagram/stories/{storyId}/insights` — Get Instagram story insights

**Inbox Analytics** (7)

- `GET /v1/analytics/inbox/volume` — Get inbox messaging volume
- `GET /v1/analytics/inbox/heatmap` — Get day × hour heatmap
- `GET /v1/analytics/inbox/source-breakdown` — Get inbox source breakdown
- `GET /v1/analytics/inbox/response-time` — Get inbox response-time stats
- `GET /v1/analytics/inbox/top-accounts` — Get top accounts by inbox volume
- `GET /v1/analytics/inbox/conversations` — List conversation analytics
- `GET /v1/analytics/inbox/conversations/{conversationId}` — Get conversation analytics

## §4 Social comments: read/reply/delete/like/hide on FB/IG posts

Tags: Comments


**Comments** (15)

- `GET /v1/inbox/comments` — List commented posts
- `GET /v1/inbox/comments/{postId}` — Get post comments
- `POST /v1/inbox/comments/{postId}` — Reply to comment
- `DELETE /v1/inbox/comments/{postId}` — Delete comment
- `PATCH /v1/inbox/comments/{postId}/{commentId}` — Edit comment
- `POST /v1/inbox/comments/{postId}/{commentId}/moderation` — Set comment moderation status
- `POST /v1/inbox/comments/{postId}/{commentId}/hide` — Hide comment
- `DELETE /v1/inbox/comments/{postId}/{commentId}/hide` — Unhide comment
- `POST /v1/inbox/comments/{postId}/{commentId}/pin` — Pin comment
- `DELETE /v1/inbox/comments/{postId}/{commentId}/pin` — Unpin comment
- `POST /v1/inbox/comments/{postId}/{commentId}/like` — Like comment
- `DELETE /v1/inbox/comments/{postId}/{commentId}/like` — Unlike comment
- `POST /v1/inbox/posts/{postId}/like` — Like post
- `DELETE /v1/inbox/posts/{postId}/like` — Unlike post
- `POST /v1/inbox/comments/{postId}/{commentId}/private-reply` — Send private reply

## §4 Comment-to-DM automations with follow gate

Tags: Comment Automations, Instagram (follow status)


**Comment Automations** (6)

- `GET /v1/comment-automations` — List comment-to-DM automations
- `POST /v1/comment-automations` — Create comment-to-DM automation
- `GET /v1/comment-automations/{automationId}` — Get automation details
- `PATCH /v1/comment-automations/{automationId}` — Update automation settings
- `DELETE /v1/comment-automations/{automationId}` — Delete automation
- `GET /v1/comment-automations/{automationId}/logs` — List automation logs

**Instagram** (5)

- `GET /v1/accounts/{accountId}/instagram/stories` — List active Instagram stories
- `GET /v1/accounts/{accountId}/instagram/publishing-limit` — Get Instagram publishing limit
- `GET /v1/accounts/{accountId}/instagram/audio` — Search Instagram audio
- `GET /v1/accounts/{accountId}/instagram/audio/{audioId}` — Get Instagram audio metadata
- `GET /v1/accounts/{accountId}/instagram/stories/{storyId}/insights` — Get Instagram story insights

## §4 Cross-platform publishing: GBP Updates/Offers, FB/IG posts, Stories, Reels; impressions/reach

Tags: Posts, Media, Queue, GMB Media, Analytics


**Posts** (10)

- `GET /v1/posts` — List posts
- `POST /v1/posts` — Create post
- `GET /v1/posts/{postId}` — Get post
- `PUT /v1/posts/{postId}` — Update post
- `DELETE /v1/posts/{postId}` — Delete post
- `POST /v1/posts/bulk-upload` — Bulk upload from CSV
- `POST /v1/posts/{postId}/retry` — Retry failed post
- `POST /v1/posts/{postId}/unpublish` — Unpublish post
- `POST /v1/posts/{postId}/edit` — Edit published post
- `POST /v1/posts/{postId}/update-metadata` — Update post metadata

**Media** (1)

- `POST /v1/media/presign` — Get upload URL

**Queue** (6)

- `GET /v1/queue/slots` — List schedules
- `POST /v1/queue/slots` — Create schedule
- `PUT /v1/queue/slots` — Update schedule
- `DELETE /v1/queue/slots` — Delete schedule
- `GET /v1/queue/preview` — Preview upcoming slots
- `GET /v1/queue/next-slot` — Get next available slot

**GMB Media** (3)

- `GET /v1/accounts/{accountId}/gmb-media` — List media
- `POST /v1/accounts/{accountId}/gmb-media` — Upload photo
- `DELETE /v1/accounts/{accountId}/gmb-media` — Delete photo

**Analytics** (26)

- `GET /v1/analytics` — Get post analytics
- `GET /v1/analytics/delta` — Analytics changed since a cursor
- `GET /v1/analytics/youtube/channel-insights` — Get YouTube channel insights
- `GET /v1/analytics/linkedin/org-aggregate-analytics` — Get LinkedIn org analytics
- `GET /v1/analytics/tiktok/account-insights` — Get TikTok account-level insights
- `GET /v1/analytics/youtube/daily-views` — Get YouTube daily views
- `GET /v1/analytics/youtube/video-retention` — Get YouTube video retention curve
- `GET /v1/analytics/facebook/page-insights` — Get Facebook Page insights
- `GET /v1/analytics/facebook/post-earnings` — Get Facebook post monetization earnings
- `GET /v1/analytics/instagram/account-insights` — Get Instagram insights
- `GET /v1/analytics/instagram/follower-history` — Get Instagram follower history
- `GET /v1/analytics/instagram/demographics` — Get Instagram demographics
- `GET /v1/analytics/youtube/demographics` — Get YouTube demographics
- `GET /v1/analytics/daily-metrics` — Get daily aggregated metrics
- `GET /v1/analytics/best-time` — Get best times to post
- `GET /v1/analytics/content-decay` — Get content performance decay
- `GET /v1/analytics/posting-frequency` — Get frequency vs engagement
- `GET /v1/analytics/post-timeline` — Get post analytics timeline
- `GET /v1/analytics/googlebusiness/performance` — Get Google Business Profile performance metrics
- `GET /v1/analytics/googlebusiness/search-keywords` — Get Google Business Profile search keywords
- `POST /v1/posts/sync-external` — Sync an external post
- `GET /v1/accounts/follower-stats` — Get follower stats
- `GET /v1/accounts/{accountId}/linkedin-aggregate-analytics` — Get LinkedIn aggregate stats
- `GET /v1/accounts/{accountId}/linkedin-post-analytics` — Get LinkedIn post stats
- `GET /v1/accounts/{accountId}/linkedin-post-reactions` — Get LinkedIn post reactions
- `GET /v1/accounts/{accountId}/facebook-post-reactions` — Get Facebook post reactions

## §2 SEO-injected replies; §6 photo repurpose

Tags: GMB Reviews (reply), Posts, Media


**GMB Reviews** (5)

- `GET /v1/accounts/{accountId}/gmb-reviews` — Get reviews
- `POST /v1/accounts/{accountId}/gmb-reviews/batch` — Batch get reviews
- `GET /v1/accounts/{accountId}/gmb-reviews/{reviewId}` — Get a review
- `POST /v1/accounts/{accountId}/gmb-reviews/{reviewId}/reply` — Reply to a review
- `DELETE /v1/accounts/{accountId}/gmb-reviews/{reviewId}/reply` — Delete a review reply

**Posts** (10)

- `GET /v1/posts` — List posts
- `POST /v1/posts` — Create post
- `GET /v1/posts/{postId}` — Get post
- `PUT /v1/posts/{postId}` — Update post
- `DELETE /v1/posts/{postId}` — Delete post
- `POST /v1/posts/bulk-upload` — Bulk upload from CSV
- `POST /v1/posts/{postId}/retry` — Retry failed post
- `POST /v1/posts/{postId}/unpublish` — Unpublish post
- `POST /v1/posts/{postId}/edit` — Edit published post
- `POST /v1/posts/{postId}/update-metadata` — Update post metadata

**Media** (1)

- `POST /v1/media/presign` — Get upload URL

## §5 SEO hub: profile copy for GBP (description, services, attributes, place actions)

Tags: GMB Location Details, GMB Attributes, GMB Services, GMB Place Actions, GMB Verifications


**GMB Location Details** (2)

- `GET /v1/accounts/{accountId}/gmb-location-details` — Get location details
- `PUT /v1/accounts/{accountId}/gmb-location-details` — Update location details

**GMB Attributes** (3)

- `GET /v1/accounts/{accountId}/gmb-attribute-metadata` — Get attribute metadata
- `GET /v1/accounts/{accountId}/gmb-attributes` — Get attributes
- `PUT /v1/accounts/{accountId}/gmb-attributes` — Update attributes

**GMB Services** (2)

- `GET /v1/accounts/{accountId}/gmb-services` — Get services
- `PUT /v1/accounts/{accountId}/gmb-services` — Replace services

**GMB Place Actions** (4)

- `GET /v1/accounts/{accountId}/gmb-place-actions` — List action links
- `POST /v1/accounts/{accountId}/gmb-place-actions` — Create action link
- `DELETE /v1/accounts/{accountId}/gmb-place-actions` — Delete action link
- `PATCH /v1/accounts/{accountId}/gmb-place-actions` — Update action link

**GMB Verifications** (4)

- `GET /v1/accounts/{accountId}/gmb-verifications` — Get verification state
- `POST /v1/accounts/{accountId}/gmb-verifications` — Start a verification
- `POST /v1/accounts/{accountId}/gmb-verifications/options` — Fetch verification options
- `POST /v1/accounts/{accountId}/gmb-verifications/{verificationId}/complete` — Complete a verification

## Connect flow: tenant links Google/Facebook/Instagram/WhatsApp

Tags: Connect, Accounts, Profiles


**Connect** (54)

- `GET /v1/connect/{platform}` — Get OAuth connect URL
- `POST /v1/connect/{platform}` — Complete OAuth callback
- `GET /v1/connect/{platform}/ads` — Connect ads for a platform
- `GET /v1/connect/meta-ads/callback` — Complete Meta business login
- `GET /v1/connect/shopify` — Get Shopify OAuth connect URL
- `POST /v1/connect/shopify/token` — Connect a Shopify store with a custom-app Admin token
- `PATCH /v1/connect/tiktok-ads` — Set TikTok brand identity
- `GET /v1/connect/facebook/select-page` — List Facebook pages
- `POST /v1/connect/facebook/select-page` — Select Facebook page
- `GET /v1/connect/instagram/select-account` — List Pages with a linked Instagram account
- `POST /v1/connect/instagram/select-account` — Select the Page whose Instagram account to connect
- `GET /v1/connect/googlebusiness/locations` — List Google Business Profile locations
- `POST /v1/connect/googlebusiness/select-location` — Select Google Business Profile location
- `GET /v1/connect/pending-data` — Get pending OAuth data
- `GET /v1/connect/linkedin/organizations` — List LinkedIn orgs
- `POST /v1/connect/linkedin/select-organization` — Select LinkedIn org
- `GET /v1/connect/pinterest/select-board` — List Pinterest boards
- `POST /v1/connect/pinterest/select-board` — Select Pinterest board
- `GET /v1/connect/snapchat/select-profile` — List Snapchat profiles
- `POST /v1/connect/snapchat/select-profile` — Select Snapchat profile
- `POST /v1/connect/bluesky/credentials` — Connect Bluesky account
- `POST /v1/connect/openai-ads/credentials` — Connect an OpenAI Ads account
- `POST /v1/connect/whatsapp/credentials` — Connect WhatsApp via credentials
- `GET /v1/connect/whatsapp/select-phone-number` — List numbers for selection
- `POST /v1/connect/whatsapp/select-phone-number` — Complete number selection
- `POST /v1/connect/whatsapp/embedded-signup` — Connect WhatsApp from Embedded Signup
- `GET /v1/connect/whatsapp/sdk-config` — Get Embedded Signup SDK config
- `POST /v1/connect/discord` — Connect a Discord channel
- `GET /v1/connect/slack` — List Slack channels for the channel picker
- `POST /v1/connect/slack` — Connect a Slack channel
- `GET /v1/connect/telegram` — Generate Telegram code
- `POST /v1/connect/telegram` — Connect Telegram directly
- `PATCH /v1/connect/telegram` — Check Telegram status
- `GET /v1/accounts/{accountId}/webhook-subscription` — Read a Facebook Page's webhook subscription
- `POST /v1/accounts/{accountId}/webhook-subscription` — Re-subscribe a Facebook Page to Zernio's webhooks
- `GET /v1/accounts/{accountId}/facebook-page` — List Facebook pages
- `PUT /v1/accounts/{accountId}/facebook-page` — Update Facebook page
- `GET /v1/accounts/{accountId}/linkedin-organizations` — List LinkedIn orgs
- `PUT /v1/accounts/{accountId}/linkedin-organization` — Switch LinkedIn account type
- `GET /v1/accounts/{accountId}/pinterest-boards` — List Pinterest boards
- `PUT /v1/accounts/{accountId}/pinterest-boards` — Set default Pinterest board
- `POST /v1/accounts/{accountId}/pinterest-boards` — Create Pinterest board
- `GET /v1/accounts/{accountId}/youtube-captions` — Get a YouTube video transcript
- `GET /v1/accounts/{accountId}/youtube-playlists` — List YouTube playlists
- `PUT /v1/accounts/{accountId}/youtube-playlists` — Set default YouTube playlist
- `GET /v1/accounts/{accountId}/gmb-locations` — List Google Business Profile locations
- `PUT /v1/accounts/{accountId}/gmb-locations` — Update Google Business Profile location
- `POST /v1/accounts/{accountId}/gmb-locations/assign` — Assign Google Business Profile location to another profile
- `GET /v1/accounts/{accountId}/reddit-subreddits` — List Reddit subreddits
- `PUT /v1/accounts/{accountId}/reddit-subreddits` — Set default subreddit
- `GET /v1/accounts/{accountId}/reddit-subreddits/{subreddit}/rules` — Get subreddit rules
- `POST /v1/accounts/{accountId}/reddit-vote` — Vote on a Reddit post or comment
- `GET /v1/accounts/{accountId}/reddit-flairs` — List subreddit flairs
- `POST /v1/accounts/{accountId}/reddit-flairs` — Set Reddit post flair

**Accounts** (14)

- `GET /v1/accounts` — List accounts
- `GET /v1/accounts/follower-stats` — Get follower stats
- `PUT /v1/accounts/{accountId}` — Update account
- `PATCH /v1/accounts/{accountId}` — Move account to another profile
- `DELETE /v1/accounts/{accountId}` — Disconnect account
- `GET /v1/accounts/health` — Check accounts health
- `GET /v1/accounts/{accountId}/health` — Check account health
- `GET /v1/accounts/{accountId}/posts` — List posts published on the platform
- `GET /v1/accounts/{accountId}/follow-status/{userId}` — Check whether an Instagram user follows the account
- `GET /v1/accounts/{accountId}/tiktok/creator-info` — Get TikTok creator info
- `GET /v1/accounts/{accountId}/slack-settings` — Get Slack account settings
- `PATCH /v1/accounts/{accountId}/slack-settings` — Update Slack account settings
- `GET /v1/accounts/{accountId}/bluesky-settings` — Get Bluesky account settings
- `PATCH /v1/accounts/{accountId}/bluesky-settings` — Update Bluesky account settings

**Profiles** (5)

- `GET /v1/profiles` — List profiles
- `POST /v1/profiles` — Create profile
- `GET /v1/profiles/{profileId}` — Get profile
- `PUT /v1/profiles/{profileId}` — Update profile
- `DELETE /v1/profiles/{profileId}` — Delete profile
