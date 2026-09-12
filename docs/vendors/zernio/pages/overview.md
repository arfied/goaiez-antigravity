# Overview

Every Zernio endpoint, grouped by resource. Pick a resource to see its operations.

import { Cards, Card } from 'fumadocs-ui/components/card';

## Core

<Cards>
  <Card title="Profiles" href="/profiles/list-profiles" description="5 endpoints. Manage profiles (named groups of accounts)." />
  <Card title="Accounts" href="/accounts/list-accounts" description="14 endpoints. Manage connected accounts: list, fetch, update, disconnect, and read account health." />
  <Card title="Connect" href="/connect/get-connect-url" description="54 endpoints. OAuth and credential flows for connecting accounts, plus per-platform selection steps (Facebook pages, Pinterest boards, LinkedIn organizations, Google Business Profile locations, etc.)." />
</Cards>

## Content & Scheduling

<Cards>
  <Card title="Posts" href="/posts/list-posts" description="10 endpoints. Create, schedule, list, update, and delete posts across all connected accounts." />
  <Card title="Queue" href="/queue/list-queue-slots" description="6 endpoints. Manage posting-queue time slots and preview the upcoming queue." />
  <Card title="Media" href="/media/get-media-presigned-url" description="1 endpoint. Upload and presign media (images, videos, documents) for use in posts." />
  <Card title="Validate" href="/validate/validate-media" description="4 endpoints. Pre-flight validation endpoints." />
</Cards>

## Inbox

<Cards>
  <Card title="Messages" href="/messages/list-inbox-conversations" description="16 endpoints. Unified inbox API for managing conversations and direct messages across all connected accounts." />
  <Card title="Comments" href="/comments/list-inbox-comments" description="15 endpoints. Unified inbox API for managing comments on posts across all connected accounts." />
  <Card title="Reviews" href="/reviews/list-inbox-reviews" description="3 endpoints. Unified inbox API for managing reviews on Facebook Pages and Google Business Profile accounts." />
  <Card title="Mentions" href="/mentions/list-inbox-mentions" description="2 endpoints. Unified inbox API for managing mentions across connected accounts." />
  <Card title="Broadcasts" href="/broadcasts/list-broadcast-recipients" description="10 endpoints. Platform-agnostic broadcast campaigns." />
  <Card title="Contacts" href="/contacts/list-contacts" description="7 endpoints. Cross-platform contact management (CRM)." />
  <Card title="Custom Fields" href="/custom-fields/list-custom-fields" description="6 endpoints. Custom field definitions for contacts." />
  <Card title="Sequences" href="/sequences/list-sequence-enrollments" description="10 endpoints. Drip campaign sequences." />
  <Card title="Workflows" href="/workflows/list-workflows" description="14 endpoints. Branching conversation automations." />
  <Card title="Comment Automations" href="/comment-automations/list-comment-automation-logs" description="6 endpoints. Comment-to-DM growth automations." />
</Cards>

## Analytics

<Cards>
  <Card title="Posting Analytics" href="/analytics/get-analytics" description="26 endpoints. Post and account analytics across platforms (insights, demographics, follower history, best time to post, content decay, and aggregated metrics)." />
  <Card title="Inbox Analytics" href="/inbox-analytics/list-inbox-conversation-analytics" description="7 endpoints" />
</Cards>

## Advertising

<Cards>
  <Card title="Campaigns and Ads" href="/ad-campaigns/get-ad-tree" description="48 endpoints. The advertising structure: campaigns, ad sets, and ads." />
  <Card title="Creatives" href="/ad-creatives/list-ad-catalog-product-sets" description="18 endpoints. Creative assets: the standalone creative library (create/reuse/rename), the ad-account image library (list + base64 upload), rendered ad previews, and product catalogs for Advantage+/dynamic ads." />
  <Card title="Audiences" href="/ad-audiences/list-ad-audiences" description="7 endpoints. Custom audiences for targeting: customer lists (hashed upload), website + engagement + lookalike audiences, and reusable saved-targeting presets." />
  <Card title="Targeting" href="/ad-targeting/search-ad-targeting" description="4 endpoints. Targeting discovery: search interests/behaviors/geo/demographics, estimate reach, and (LinkedIn) bid pricing and supply forecasts." />
  <Card title="Ad Library" href="/ad-library/search-ad-library" description="1 endpoint. Competitor and market research over the public ad archives (Meta Ad Library, LinkedIn Ad Library), searched with the customer's own connected token." />
  <Card title="Insights" href="/ad-insights/list-local-services-lead-conversations" description="10 endpoints. Measurement: cached aggregate analytics per ad/campaign, plus live Meta Graph insight queries (arbitrary fields, breakdowns, filtering, attribution windows) and async report runs." />
  <Card title="Conversions API" href="/conversions/list-conversion-actions" description="14 endpoints. Server-side Conversions API: send + adjust conversion events (with hashed matching and consent/LDU forwarding), read Event Match Quality, and manage conversion destinations (pixels/datasets) and their ad-account associations." />
  <Card title="Messaging & Call Ads" href="/messaging-ads/create-call-ad" description="2 endpoints. Click-to-message and click-to-call destination ads: WhatsApp (CTWA), Messenger, Instagram Direct, and Call ads." />
  <Card title="Reach & Frequency" href="/reach-and-frequency/create-rf-prediction" description="4 endpoints. Fixed-price reserved (Reach & Frequency) buying: quote a prediction, reserve price + inventory, and buy via a RESERVED campaign." />
  <Card title="Lead Gen" href="/lead-gen/list-form-leads" description="7 endpoints. Instant lead forms on Facebook Pages: create/list/archive forms and retrieve (or test) their leads." />
  <Card title="Accounts & Ops" href="/ad-accounts/list-ad-accounts" description="46 endpoints. Ad accounts and operational/diagnostic reads: list accounts, account finances, change/audit log, A/B studies, high-demand periods, ad labels, DSA defaults + recommendations, and Business Managers (Meta) / Business Centers (TikTok)." />
  <Card title="Pixels & Tracking Tags" href="/tracking-tags/list-tracking-tag-shared-accounts" description="10 endpoints. Manage the platform measurement tag: the thing you create, install on a website, send events to, and target ads against." />
</Cards>

## Platform APIs

<Cards>
  <Card title="Instagram" href="/instagram/list-instagram-stories" description="5 endpoints. Instagram-specific read endpoints: list a connected account's Stories and fetch per-Story insights." />
  <Card title="WhatsApp" href="/whatsapp/get-whatsapp-templates" description="75 endpoints. WhatsApp Business API." />
  <Card title="Meta Business Agent" href="/business-agent/get-business-agent-status" description="55 endpoints. Provision and operate Meta Business Agent, Meta's own AI agent, on a connected WhatsApp number without the merchant opening Business Manager." />
  <Card title="Google Business Profile" href="/google-business/get-google-business-reviews" description="25 endpoints" />
  <Card title="Discord" href="/discord/get-discord-settings" description="24 endpoints. Discord-specific endpoints for managing webhook identity (display name and avatar), switching channels, and listing guild channels." />
  <Card title="Slack" href="/slack/list-slack-members" description="1 endpoint" />
  <Card title="LinkedIn Mentions" href="/linkedin-mentions/get-linkedin-mentions" description="1 endpoint. Resolve LinkedIn organization and person mentions for use in posts." />
  <Card title="Reddit Search" href="/reddit-search/search-reddit" description="2 endpoints. Search Reddit posts and browse subreddit feeds." />
  <Card title="Twitter Engagement" href="/twitter-engagement/search-tweets" description="8 endpoints. X-specific engagement endpoints for retweeting, bookmarking, and following." />
  <Card title="Blogs" href="/blogs/list-blogs" description="10 endpoints. Manage blogs and blog articles on connected accounts." />
</Cards>

## Telephony

<Cards>
  <Card title="Phone Numbers" href="/phone-numbers/list-phone-numbers" description="28 endpoints. Buy and manage phone numbers." />
  <Card title="Call History" href="/calls/list-calls" description="3 endpoints. Unified call history across every number you own: WhatsApp Business Calling and regular phone (PSTN) calls in one list, newest first, without fanning out one request per number." />
  <Card title="Voice & Calling" href="/voice/list-voice-calls" description="18 endpoints. Regular phone (PSTN) calling on your numbers." />
  <Card title="SMS" href="/sms/send-sms" description="22 endpoints. SMS/MMS on your numbers: enable SMS on a number, send messages, validate recipient numbers, export STOP opt-outs, and complete the US carrier registration (10DLC or toll-free) required before US traffic delivers." />
  <Card title="Verify" href="/verify/create-verification" description="3 endpoints. Managed one-time passcodes (OTP) for phone verification." />
</Cards>

## Developer

<Cards>
  <Card title="API Keys" href="/api-keys/list-api-keys" description="4 endpoints. Create, list, and revoke API keys used to authenticate requests." />
  <Card title="Webhooks" href="/webhooks/create-webhook-settings" description="7 endpoints. Configure webhooks for real-time notifications." />
  <Card title="Logs" href="/logs/list-logs" description="1 endpoint. Publishing logs for transparency and debugging." />
  <Card title="Usage" href="/usage/get-billing" description="5 endpoints. Usage and metering." />
</Cards>

## Settings & Admin

<Cards>
  <Card title="Account Settings" href="/account-settings/get-messenger-menu" description="9 endpoints. Platform-specific account settings: Facebook persistent menu, Instagram ice breakers, and Telegram bot commands." />
  <Card title="Account Groups" href="/account-groups/list-account-groups" description="4 endpoints. Manage account groups (collections of accounts used for cross-posting and organization)." />
  <Card title="Connected Apps" href="/connected-apps/list-connected-apps" description="2 endpoints. List and revoke the OAuth clients (AI assistants and MCP connectors) authorized on the account." />
  <Card title="Users" href="/users/list-users" description="2 endpoints. Read the authenticated user and team members." />
  <Card title="Invites" href="/invites/create-invite-token" description="1 endpoint. Generate invite tokens for adding members to a team." />
  <Card title="Tools" href="/tools/download-tiktok-video" description="1 endpoint. Media tools for authenticated API consumers." />
</Cards>

---
