# Connect WhatsApp via credentials API Reference

Connect a WhatsApp Business Account by providing Meta credentials directly.
This is the headless alternative to the Embedded Signup browser flow.

To get the required credentials:
1. Go to Meta Business Suite (business.facebook.com)
2. Create or select a WhatsApp Business Account
3. In Business Settings > System Users, create a System User
4. Assign it the whatsapp_business_management and whatsapp_business_messaging permissions
5. Generate a permanent access token
6. Get the WABA ID from WhatsApp Manager > Account Tools > Phone Numbers
7. Get the Phone Number ID from the same page (click on the number)

Warning: connecting subscribes your own Meta app to this WABA with an override
callback that redirects its webhook delivery to Zernio. This WABA's events stop
reaching any callback URL you had configured before, immediately and with no
overlap window. Do not unsubscribe your app from the WABA afterward: that also
cuts off Zernio's delivery, and recovery requires calling this endpoint again.


## POST /v1/connect/whatsapp/credentials

**Connect WhatsApp via credentials**

Connect a WhatsApp Business Account by providing Meta credentials directly.
This is the headless alternative to the Embedded Signup browser flow.

To get the required credentials:
1. Go to Meta Business Suite (business.facebook.com)
2. Create or select a WhatsApp Business Account
3. In Business Settings > System Users, create a System User
4. Assign it the whatsapp_business_management and whatsapp_business_messaging permissions
5. Generate a permanent access token
6. Get the WABA ID from WhatsApp Manager > Account Tools > Phone Numbers
7. Get the Phone Number ID from the same page (click on the number)

Warning: connecting subscribes your own Meta app to this WABA with an override
callback that redirects its webhook delivery to Zernio. This WABA's events stop
reaching any callback URL you had configured before, immediately and with no
overlap window. Do not unsubscribe your app from the WABA afterward: that also
cuts off Zernio's delivery, and recovery requires calling this endpoint again.


### Request Body

- **profileId** (required) `string`: Your Zernio profile ID
- **accessToken** (required) `string`: Permanent System User access token from Meta Business Suite
- **wabaId** (required) `string`: WhatsApp Business Account ID from Meta
- **phoneNumberId** (required) `string`: Phone Number ID from Meta WhatsApp Manager
- **pin** `string`: The 6-digit two-step verification PIN set on the number. Required if you enabled two-step verification for it, otherwise Meta rejects the Cloud API registration with error 133005 and the number cannot send messages.

### Responses

#### 200: WhatsApp connected successfully

**Response Body:**

- **message** `string`: No description
- **registrationWarning** `string`: Present when the account was created but Meta rejected the Cloud API registration. The number cannot send messages until this is resolved.
- **webhookNotice** `string`: Present when the WABA webhook subscription (with the Zernio override callback) succeeded. Explains the delivery cutover and warns against unsubscribing the app from the WABA afterward.
- **account** `object`: 
  - **accountId** `string`: No description
  - **platform** `string`: No description - one of: whatsapp
  - **username** `string`: Display phone number
  - **displayName** `string`: Meta-verified business name
  - **isActive** `boolean`: No description
  - **phoneNumber** `string`: The connected phone number
  - **verifiedName** `string`: Meta-verified business name for the phone number
  - **qualityRating** `string`: Meta quality rating for the phone number (e.g. GREEN, YELLOW, RED, UNKNOWN)

#### 400: Invalid request. Missing fields, a `pin` that is not 6 digits, or the
phoneNumberId was not found in the specified WABA. If the phone was not
found, the response includes availablePhoneNumbers to help identify the
correct ID.


#### 401: Invalid or expired access token

#### 403: Profile limit exceeded for this plan

#### 409: Conflict with an existing connection. Either the phone number is a Zernio-provisioned number pinned to a different profile (code WHATSAPP_NUMBER_PINNED_TO_PROFILE, connect it from that profile or move it first with PATCH /v1/whatsapp/phone-numbers/{id}/profile), or the number is already actively connected on another profile or team (code WHATSAPP_NUMBER_ALREADY_CONNECTED, disconnect it there first). A number can only be live on one profile.

---

---
