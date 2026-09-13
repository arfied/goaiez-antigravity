# SMS

Send and receive SMS and MMS from your numbers with POST /v1/sms/messages, with US carrier registration handled through the API.

import { Cards, Card } from 'fumadocs-ui/components/card';
import { ClipboardCheck, Send } from 'lucide-react';
import { PlatformCapabilities } from '@/components/platform-capabilities';

Send and receive SMS and MMS from your [numbers](/platforms/phone-numbers) with `POST /v1/sms/messages`. Replies land in the same [inbox](/messages/list-inbox-conversations) as your other channels and arrive as [`message.received`](/webhooks/inbox#messagereceived) events. In the US a number needs an approved carrier registration before messages deliver; Zernio runs that registration through the API.

## Quick reference

<PlatformCapabilities rows={[
  { property: 'Message types', value: 'SMS (text) and MMS (media)' },
  { property: 'From', value: 'Any SMS-enabled number on your team, or an alphanumeric sender ID' },
  { property: 'Number format', value: 'E.164, normalized automatically' },
  { property: 'Replies', value: 'Thread into the inbox; message.received webhook' },
  { property: 'US requirement', value: 'Approved 10DLC or toll-free registration' },
  { property: 'Registration reuse', value: 'One approval covers many numbers, with no repeat brand fee' },
  { property: 'Idempotency', value: 'Idempotency-Key header' },
]} />

## Before you start

SMS requires usage-based billing. In the US, messages do not deliver from a number until its carrier registration (10DLC or toll-free) is approved; approval is asynchronous, so register early. Every other country sends as soon as SMS is enabled on the number.

## How it fits together

1. Get an SMS-capable number. Not every number can text: [search](/platforms/phone-numbers/provisioning#step-1-search-available-numbers) with `sms=true`, purchase with `wantsSms: true`, or [port in](/platforms/phone-numbers/porting).
2. Enable SMS and register. Call `POST /v1/phone-numbers/{id}/sms`, then run the US [carrier registration](/platforms/sms/registration). An approved registration you already hold [covers the new number](/platforms/sms/registration#reuse-an-approval-skip-the-fee) with no repeat fee.
3. Send. [Send SMS or MMS](/platforms/sms/sending), respect opt-outs, and read replies in the inbox. For one-way international sends from a brand name instead of a number, use a [sender ID](/platforms/sms/sender-ids).

## In this section

<Cards>
  <Card icon={<ClipboardCheck />} title="Carrier registration" href="/platforms/sms/registration" description="10DLC and toll-free registration, reuse, and appeals" />
  <Card icon={<Send />} title="Sending" href="/platforms/sms/sending" description="Send SMS and MMS, handle opt-outs, and look up numbers" />
  <Card icon={<Send />} title="Sender IDs" href="/platforms/sms/sender-ids" description="Branded one-way SMS from a name like ZERNIO" />
</Cards>

## Related

- [Send an SMS/MMS](/sms/send-sms): every field of the request.
- [Start a carrier registration](/sms/start-sms-registration): 10DLC and toll-free.
- [Add a number to an existing registration](/sms/reuse-sms-registration-for-number): reuse an approval.
- [List SMS opt-outs](/sms/list-sms-opt-outs): recipients who replied STOP.
- [Inbox webhooks](/webhooks/inbox): `message.received`, `message.delivered` and `message.failed`.

---
