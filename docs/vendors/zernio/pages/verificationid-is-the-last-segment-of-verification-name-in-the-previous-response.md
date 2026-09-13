# verificationId is the last segment of verification.name in the previous response
curl -X POST https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/gmb-verifications/4T1775504407481/complete \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "pin": "123456" }'
```
</Tab>
</Tabs>

Response (`200`) of the state call, for a location that failed an SMS verification and has none pending:

```json
{
  "success": true,
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "locationId": "12345678901234567890",
  "voiceOfMerchantState": {
    "hasVoiceOfMerchant": false,
    "hasBusinessAuthority": true,
    "verify": { "hasPendingVerification": false }
  },
  "verifications": [
    {
      "name": "locations/12345678901234567890/verifications/4T1775504407480",
      "method": "SMS",
      "state": "FAILED",
      "createTime": "2026-04-06T19:40:07.480Z"
    }
  ]
}
```

Response (`200`) of the options call. `verificationMethod` is `ADDRESS`, `EMAIL`, `PHONE_CALL`, `SMS`, `AUTO` or `VETTED_PARTNER`, and calls and SMS carry the `phoneNumber` Google will use:

```json
{
  "success": true,
  "options": [
    { "verificationMethod": "SMS", "phoneNumber": "+14155550123" },
    { "verificationMethod": "ADDRESS" }
  ]
}
```

Response (`200`) of the start call. The last segment of `verification.name` is the `verificationId` the complete call needs:

```json
{
  "success": true,
  "verification": {
    "name": "locations/12345678901234567890/verifications/4T1775504407481",
    "method": "SMS",
    "state": "PENDING",
    "createTime": "2026-04-07T10:12:00Z"
  }
}
```

Response (`200`) of the complete call: the same `verification`, with `state` `COMPLETED` when Google accepts the pin and `FAILED` when it does not.

Reviews, edits and other listing data only surface once a location is matched to a published Maps place (it has a `placeId`, see [Location details](#location-details)) and has Voice of Merchant. When `GET /gmb-reviews` returns an empty array for a listing that shows reviews on Google, check `hasVoiceOfMerchant` here first.

## Location details

`GET /v1/accounts/{accountId}/gmb-location-details` returns the listing ([Get location details](/google-business/get-google-business-location-details)). `readMask` picks the fields: `name`, `title`, `regularHours`, `specialHours`, `profile`, `websiteUri`, `phoneNumbers`, `categories`, `serviceArea`, `serviceItems`, `storefrontAddress`, `openInfo`, `metadata`, `moreHours`. The `location` block is always present and carries `placeId`, the public `reviewUrl` (the "write a review" link, handy behind a QR code), `mapsUri` and `isVerified`. `PUT` on the same path updates any field named in `updateMask` ([Update location details](/google-business/update-google-business-location-details)).

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: details } = await zernio.gmblocationdetails.getGoogleBusinessLocationDetails({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' },
  query: { readMask: 'regularHours,specialHours,profile,websiteUri' }
});

console.log(details.location.reviewUrl, details.regularHours);

await zernio.gmblocationdetails.updateGoogleBusinessLocationDetails({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' },
  body: {
    updateMask: 'regularHours',
    regularHours: {
      periods: [
        { openDay: 'MONDAY', openTime: '09:00', closeDay: 'MONDAY', closeTime: '17:00' },
        { openDay: 'TUESDAY', openTime: '09:00', closeDay: 'TUESDAY', closeTime: '17:00' }
      ]
    }
  }
});
```
</Tab>
<Tab value="Python">
```python
details = client.gmb_location_details.get_google_business_location_details(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    read_mask="regularHours,specialHours,profile,websiteUri"
)

print(details["location"]["reviewUrl"], details["regularHours"])

client.gmb_location_details.update_google_business_location_details(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    update_mask="regularHours",
    regular_hours={
        "periods": [
            {"openDay": "MONDAY", "openTime": "09:00", "closeDay": "MONDAY", "closeTime": "17:00"},
            {"openDay": "TUESDAY", "openTime": "09:00", "closeDay": "TUESDAY", "closeTime": "17:00"}
        ]
    }
)
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/gmb-location-details?readMask=regularHours,specialHours,profile,websiteUri" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"

curl -X PUT https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/gmb-location-details \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "updateMask": "regularHours",
    "regularHours": {
      "periods": [
        {"openDay": "MONDAY", "openTime": "09:00", "closeDay": "MONDAY", "closeTime": "17:00"},
        {"openDay": "TUESDAY", "openTime": "09:00", "closeDay": "TUESDAY", "closeTime": "17:00"}
      ]
    }
  }'
```
</Tab>
</Tabs>

Response (`200`) of the read:

```json
{
  "success": true,
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "locationId": "12345678901234567890",
  "location": {
    "name": "Joe's Pizza",
    "placeId": "ChIJExampleJoesPizzaPlaceId",
    "reviewUrl": "https://search.google.com/local/writereview?placeid=ChIJExampleJoesPizzaPlaceId",
    "mapsUri": "https://maps.google.com/maps?cid=1234567890123456789",
    "isVerified": true
  },
  "title": "Joe's Pizza",
  "regularHours": {
    "periods": [
      { "openDay": "MONDAY", "openTime": "11:00", "closeDay": "MONDAY", "closeTime": "22:00" }
    ]
  },
  "specialHours": {
    "specialHourPeriods": [{ "startDate": { "year": 2026, "month": 12, "day": 25 }, "closed": true }]
  },
  "profile": { "description": "Authentic New York style pizza since 1985" },
  "websiteUri": "https://joespizza.com"
}
```

Response (`200`) of the update:

```json
{
  "success": true,
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "locationId": "12345678901234567890"
}
```

For an unverified or new location Google omits `placeId`, `reviewUrl` and `mapsUri`, so they come back `null` and `isVerified` is `false`.

## Photos

`GET /v1/accounts/{accountId}/gmb-media` lists the listing's photos and `POST` adds one from a public URL ([Media](/google-business/list-google-business-media)). `category` decides where the photo appears: `COVER`, `PROFILE`, `LOGO`, `EXTERIOR`, `INTERIOR`, `PRODUCT`, `FOOD_AND_DRINK`, `MENU`, `COMMON_AREA`, `ROOMS`, `TEAMS`, `AT_WORK` or `ADDITIONAL`. `DELETE` with `mediaId` removes one.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: media } = await zernio.gmbmedia.listGoogleBusinessMedia({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
});

const { data: photo } = await zernio.gmbmedia.createGoogleBusinessMedia({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' },
  body: {
    sourceUrl: 'https://cdn.example.com/photos/interior.jpg',
    description: 'Dining area with outdoor seating',
    category: 'INTERIOR'
  }
});

console.log(media.totalMediaItemsCount, photo.googleUrl);
```
</Tab>
<Tab value="Python">
```python
media = client.gmb_media.list_google_business_media(account_id="66b2e19d8c3f5a7e9d0b1c2d")

photo = client.gmb_media.create_google_business_media(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    source_url="https://cdn.example.com/photos/interior.jpg",
    description="Dining area with outdoor seating",
    category="INTERIOR"
)

print(media["totalMediaItemsCount"], photo["googleUrl"])
```
</Tab>
<Tab value="curl">
```bash
curl https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/gmb-media \
  -H "Authorization: Bearer $ZERNIO_API_KEY"

curl -X POST https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/gmb-media \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "sourceUrl": "https://cdn.example.com/photos/interior.jpg",
    "description": "Dining area with outdoor seating",
    "category": "INTERIOR"
  }'
```
</Tab>
</Tabs>

Response (`200`) of the list:

```json
{
  "success": true,
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "locationId": "12345678901234567890",
  "mediaItems": [
    {
      "name": "accounts/123456789/locations/12345678901234567890/media/AF1QipN...",
      "mediaFormat": "PHOTO",
      "googleUrl": "https://lh3.googleusercontent.com/...",
      "description": "Storefront at dusk",
      "createTime": "2026-11-02T09:14:00Z",
      "locationAssociation": { "category": "EXTERIOR" }
    }
  ],
  "totalMediaItemsCount": 37
}
```

Response (`200`) of the upload:

```json
{
  "success": true,
  "name": "accounts/123456789/locations/12345678901234567890/media/AF1QipM...",
  "mediaFormat": "PHOTO",
  "googleUrl": "https://lh3.googleusercontent.com/..."
}
```

## Attributes

`GET /v1/accounts/{accountId}/gmb-attributes` returns the amenities, services and payment types set on the listing, and `PUT` updates the ones named in `attributeMask` ([Attributes](/google-business/get-google-business-attributes)). Which attributes exist depends on the business category; [Get attribute metadata](/google-business/get-gmb-attribute-metadata) lists the valid names, value types and enum values for a location or a category. Common ones are `has_dine_in`, `has_takeout`, `has_delivery`, `has_wifi`, `has_outdoor_seating` and `pay_credit_card_types_accepted`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: attributes } = await zernio.gmbattributes.getGoogleBusinessAttributes({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
});

await zernio.gmbattributes.updateGoogleBusinessAttributes({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' },
  body: {
    attributes: [
      { name: 'has_delivery', values: [true] },
      { name: 'has_outdoor_seating', values: [true] }
    ],
    attributeMask: 'has_delivery,has_outdoor_seating'
  }
});

console.log(attributes.attributes);
```
</Tab>
<Tab value="Python">
```python
attributes = client.gmb_attributes.get_google_business_attributes(
    account_id="66b2e19d8c3f5a7e9d0b1c2d"
)

client.gmb_attributes.update_google_business_attributes(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    attributes=[
        {"name": "has_delivery", "values": [True]},
        {"name": "has_outdoor_seating", "values": [True]}
    ],
    attribute_mask="has_delivery,has_outdoor_seating"
)

print(attributes["attributes"])
```
</Tab>
<Tab value="curl">
```bash
curl https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/gmb-attributes \
  -H "Authorization: Bearer $ZERNIO_API_KEY"

curl -X PUT https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/gmb-attributes \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "attributes": [
      {"name": "has_delivery", "values": [true]},
      {"name": "has_outdoor_seating", "values": [true]}
    ],
    "attributeMask": "has_delivery,has_outdoor_seating"
  }'
```
</Tab>
</Tabs>

Response (`200`) of the read:

```json
{
  "success": true,
  "attributes": [
    { "name": "has_delivery", "valueType": "BOOL", "values": [true] },
    { "name": "has_takeout", "valueType": "BOOL", "values": [true] },
    { "name": "has_outdoor_seating", "valueType": "BOOL", "values": [true] },
    {
      "name": "pay_credit_card_types_accepted",
      "valueType": "REPEATED_ENUM",
      "repeatedEnumValue": { "setValues": ["visa", "mastercard", "amex"] }
    }
  ]
}
```

Response (`200`) of the update: `success`, `accountId`, `locationId` and the `attributes` array as Google stored it.

## Action links

Action links are the booking, ordering and reservation buttons on the listing. `GET /v1/accounts/{accountId}/gmb-place-actions` lists them, `POST` creates one with a `uri` and a `placeActionType` (`APPOINTMENT`, `ONLINE_APPOINTMENT`, `DINING_RESERVATION`, `FOOD_ORDERING`, `FOOD_DELIVERY`, `FOOD_TAKEOUT`, `SHOP_ONLINE`), `PATCH` changes the `uri` or type of an existing link by its `name`, and `DELETE` with `name` removes it ([Action links](/google-business/list-google-business-place-actions)).

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: link } = await zernio.gmbplaceactions.createGoogleBusinessPlaceAction({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' },
  body: { uri: 'https://order.ubereats.com/joespizza', placeActionType: 'FOOD_ORDERING' }
});

await zernio.gmbplaceactions.updateGoogleBusinessPlaceAction({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' },
  body: { name: link.name, uri: 'https://order.doordash.com/joespizza' }
});
```
</Tab>
<Tab value="Python">
```python
link = client.gmb_place_actions.create_google_business_place_action(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    uri="https://order.ubereats.com/joespizza",
    place_action_type="FOOD_ORDERING"
)

client.gmb_place_actions.update_google_business_place_action(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    name=link["name"],
    uri="https://order.doordash.com/joespizza"
)
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/gmb-place-actions \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "uri": "https://order.ubereats.com/joespizza", "placeActionType": "FOOD_ORDERING" }'

curl -X PATCH https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/gmb-place-actions \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "name": "locations/12345678901234567890/placeActionLinks/456", "uri": "https://order.doordash.com/joespizza" }'
```
</Tab>
</Tabs>

Response (`200`) of the create:

```json
{
  "success": true,
  "name": "locations/12345678901234567890/placeActionLinks/456",
  "uri": "https://order.ubereats.com/joespizza",
  "placeActionType": "FOOD_ORDERING"
}
```

Response (`200`) of the update: the same 4 fields, with the `uri` you sent.

## If it fails

Every endpoint here proxies Google, so a `400` carries Google's own complaint. On `GET /gmb-location-details` it means `readMask` names a field Google does not know, most often `location`, which is a response-only block:

```json
{
  "error": "Request contains an invalid argument.",
  "code": "gbp_bad_request"
}
```

Remove the name from `readMask` and retry; the `PUT` on the same path answers `400` when `updateMask` is missing. The other surfaces fail their own way:

- Verification: `POST /gmb-verifications/options` answers `400` without `languageCode`, and for a service-area business without `context` carrying its address. On the complete call a `400` means the PIN is wrong or the verification is no longer `PENDING`.
- Photos: `POST /gmb-media` answers `400` when the media format is not one Google accepts, or the URL is not one it can fetch.
- Attributes: the `PUT` answers `400` when an attribute name or value is not one the location's category offers.
- Action links: `PATCH /gmb-place-actions` answers `400` with "At least one of uri or placeActionType is required" when the body carries neither.

A `401` with code `token_invalid` on any endpoint here means Google revoked the token: reconnect the account. A `403` means the Google login has no permission on this location. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Location details](/google-business/get-google-business-location-details), [Verifications](/google-business/get-google-business-verifications), [Media](/google-business/list-google-business-media), [Attributes](/google-business/get-google-business-attributes) and [Action links](/google-business/list-google-business-place-actions): the full schemas.
- [Services & Food Menus](/platforms/google-business/services-menus): the service list and the menus of a food business.
- [Multi-Location Posting](/platforms/google-business/multi-location): the `locationId` values these endpoints accept.
- [Inbox](/platforms/google-business/inbox): reviews, which need Voice of Merchant.
- [Analytics](/platforms/google-business/analytics): performance metrics for the listing.

---
