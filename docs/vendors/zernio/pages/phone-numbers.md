# Phone Numbers

Buy or port in a real phone number with the Zernio API, then turn on Calls, SMS and WhatsApp on that same number.

import { Cards, Card } from 'fumadocs-ui/components/card';
import { ArrowRightLeft, Globe, ShieldCheck, ShoppingCart } from 'lucide-react';
import { PlatformCapabilities } from '@/components/platform-capabilities';

Buy a phone number with `POST /v1/phone-numbers/purchase`, or port one in, then turn on the features you need on that same number. A number is the unit of telephony on Zernio:

- Calls (PSTN voice) are on by default on every number.
- SMS (text and MMS) is enabled per number; it needs an SMS-capable number, plus a carrier registration in the US.
- WhatsApp connects the number to a WhatsApp Business Account.

One number can do all 3 or only one. This section covers getting and managing the number; what you do with it lives in [Voice & Calls](/platforms/voice), [SMS](/platforms/sms) and [WhatsApp](/platforms/whatsapp). There is no separate "WhatsApp number": a WhatsApp number is a phone number with WhatsApp enabled on it. The older WhatsApp-scoped number endpoints are deprecated aliases kept for existing integrations; new code uses `/v1/phone-numbers`.

## Quick reference

<PlatformCapabilities rows={[
  { property: 'Countries', value: <><NumberCountryCount />, plus <PreOrderCountryCount /> more by pre-order (see Availability)</> },
  { property: 'Default country', value: 'United States' },
  { property: 'Price', value: '$3 to $30 per month per number, by country and number type' },
  { property: 'Calls (PSTN)', value: 'Every number, on by default' },
  { property: 'SMS', value: 'Per number, SMS-capable countries only' },
  { property: 'WhatsApp', value: 'Connect the number to a WhatsApp Business Account' },
  { property: 'Get a number', value: 'Purchase from inventory, pre-order, or port one in' },
  { property: 'Number limit', value: 'Your profile limit, and 50 where profiles are uncapped' },
]} />

## Before you start

Telephony (numbers, voice, SMS) requires usage-based billing with a payment method on file. With that in place every country is available, numbers provision inline with no separate checkout, and each number bills per month on your usage-based invoice. Calls and SMS on a number meter on the same invoice.

## The lifecycle

1. See what is available. [Availability](/platforms/phone-numbers/availability) lists the countries, number types, per-country prices and which capabilities each supports.
2. Get a number. [Purchase](/platforms/phone-numbers/provisioning) one from inventory (it provisions in the same request, auto-assigned or the exact number you pick) or [port in](/platforms/phone-numbers/porting) a number you already own. Regulated countries need a one-time [KYC](/platforms/phone-numbers/kyc) form first, and when one is out of stock (or stocked nowhere) that same form [pre-orders](/platforms/phone-numbers/kyc#out-of-stock-pre-order-it) the number.
3. Turn on features. Calls are already on. [Enable SMS](/platforms/sms/registration) (US numbers need a carrier registration) or [connect WhatsApp](/platforms/whatsapp/connection).
4. Use it. Place and receive [calls](/platforms/voice), send [texts](/platforms/sms), and read one [call history](/platforms/voice/history) across every number and channel.

## API conventions

- A number is the unit; features are sub-resources on it. Enable or disable a capability with `POST` or `DELETE` on `/v1/phone-numbers/{id}/{feature}` (`/{id}/voice`, `/{id}/sms`). Activity lives on its own top-level resources: call history at `/v1/calls`, texts at `/v1/sms/messages`, outbound calls at `/v1/voice/calls`.
- State-changing actions read as `POST /resource/{id}/{verb}`: `POST /v1/voice/calls/{id}/end`, `/transfer`, `POST /v1/sms/registrations/{id}/verify-otp`, `/appeal`.

## In this section

<Cards>
  <Card icon={<Globe />} title="Availability and pricing" href="/platforms/phone-numbers/availability" description="Which countries, number types and capabilities, and what each costs" />
  <Card icon={<ShoppingCart />} title="Buying a number" href="/platforms/phone-numbers/provisioning" description="Search inventory, purchase, and manage numbers" />
  <Card icon={<ArrowRightLeft />} title="Porting" href="/platforms/phone-numbers/porting" description="Bring an existing number over from another carrier" />
  <Card icon={<ShieldCheck />} title="KYC (regulated countries)" href="/platforms/phone-numbers/kyc" description="Collect identity details where a country requires them" />
</Cards>

## Related

- [List offerable countries](/phone-numbers/list-phone-number-countries): live prices and capability flags.
- [Search available numbers](/phone-numbers/search-available-phone-numbers): inventory in a country.
- [Purchase phone number](/phone-numbers/purchase-phone-number): every field of the request.
- [List phone numbers](/phone-numbers/list-phone-numbers): every number on your team.
- [Phone number webhooks](/webhooks/phone-numbers): activation, decline, suspension and release events.

---
