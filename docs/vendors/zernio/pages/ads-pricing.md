# Ads Pricing

Managed ads bill per active ad per month. The first 500 are free, only running ads count, and there is no percentage of ad spend.

Ads created or synced through the [Ads API](/ad-campaigns/list-ads) meter per active ad under management: the first 500 are free, then $0.01 per active ad per month ($10 per 1,000). One meter covers all 7 ad platforms: Meta (`metaads`), Google (`googleads`), LinkedIn (`linkedinads`), TikTok (`tiktokads`), Pinterest (`pinterestads`), X (`xads`) and OpenAI Ads (`openaiads`).

## What counts as a managed ad

An ad counts only while it is running or in review, the states Zernio syncs for you (delivery status, spend, daily metrics, conversions).

| Ad state | Bills? |
|---|---|
| Active (delivering) | Yes |
| In review | Yes |
| Paused | No |
| Ended or completed | No |
| Rejected or errored | No |
| Deleted | No |

The count is live and metered daily: pause or end a campaign and its ads leave the meter the same day. Nothing is retroactive, and there is no percentage of ad spend. A $5 per day ad and a $5,000 per day ad both cost $0.01 per month to manage. No endpoint reports the metered count itself: `GET /v1/ads?status=active` returns the running count of one billable state in `pagination.total`, and `status=pending_review` returns the other.

## Examples

| Active ads under management | Monthly cost |
|---|---|
| 400 | Free |
| 1,000 | $5.00 |
| 5,000 | $45.00 |
| 50,000 | $495.00 |

## Scope and mechanics

- The count spans all connected ad accounts in your team; team members' ad accounts roll up to the team owner's invoice.
- Ad creation, boosting, editing, analytics, breakdowns, audiences, lead forms and conversions carry no separate charge. The managed-ad meter is the only ads line.
- With no card on file, Zernio emails the team owner at 375 live ads, and ad syncing and ad changes then fail with `403`, code `ads_allowance_exceeded`, once the team reaches 500. With a card on file nothing pauses and every ad past 500 meters at $0.01 per month.
- It appears on your invoice as Managed Ads, itemized like every other meter. See [How billing works](/billing) for the mechanics.

---
