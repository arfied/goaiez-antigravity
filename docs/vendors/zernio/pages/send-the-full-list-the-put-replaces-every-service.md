# Send the full list: the PUT replaces every service
curl -X PUT https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/gmb-services \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "serviceItems": [
      {
        "freeFormServiceItem": {
          "category": "categories/gcid:plumber",
          "label": {"displayName": "Pipe Repair", "description": "Emergency and scheduled pipe repair"}
        },
        "price": {"currencyCode": "USD", "units": "150"}
      }
    ]
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
  "services": [
    {
      "freeFormServiceItem": {
        "category": "categories/gcid:plumber",
        "label": { "displayName": "Pipe Repair", "description": "Emergency and scheduled pipe repair" }
      },
      "price": { "currencyCode": "USD", "units": "150" }
    }
  ]
}
```

Response (`200`) of the update: `success` and the `services` array as Google stored it, which is the list to send back on the next `PUT`.

## Food menus

For locations that support menus (restaurants, cafes), `GET /v1/accounts/{accountId}/gmb-food-menus` returns the menus and `PUT` updates them with the full `menus` array and an `updateMask` ([Food menus](/google-business/get-google-business-food-menus)). A menu item's `attributes` take a `price` with a currency code, `dietaryRestriction` (`VEGETARIAN`, `VEGAN`, `GLUTEN_FREE`), `allergen` (`DAIRY`, `GLUTEN`, `SHELLFISH`, ...), `spiciness`, `servesNumPeople`, `preparationMethods` and `mediaKeys` for item photos. Give an item `options` when it comes in variants: each entry carries its own `labels` and `attributes`, so a size can set its own `price`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: menus } = await zernio.gmbfoodmenus.getGoogleBusinessFoodMenus({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
});

await zernio.gmbfoodmenus.updateGoogleBusinessFoodMenus({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' },
  body: {
    menus: [{
      labels: [{ displayName: 'Lunch Menu', languageCode: 'en' }],
      sections: [{
        labels: [{ displayName: 'Appetizers' }],
        items: [{
          labels: [{ displayName: 'Caesar Salad', description: 'Romaine, parmesan, croutons' }],
          attributes: {
            price: { currencyCode: 'USD', units: '12' },
            dietaryRestriction: ['VEGETARIAN']
          }
        }]
      }]
    }],
    updateMask: 'menus'
  }
});

console.log(menus.menus);
```
</Tab>
<Tab value="Python">
```python
menus = client.gmb_food_menus.get_google_business_food_menus(
    account_id="66b2e19d8c3f5a7e9d0b1c2d"
)

client.gmb_food_menus.update_google_business_food_menus(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    menus=[{
        "labels": [{"displayName": "Lunch Menu", "languageCode": "en"}],
        "sections": [{
            "labels": [{"displayName": "Appetizers"}],
            "items": [{
                "labels": [{"displayName": "Caesar Salad", "description": "Romaine, parmesan, croutons"}],
                "attributes": {
                    "price": {"currencyCode": "USD", "units": "12"},
                    "dietaryRestriction": ["VEGETARIAN"]
                }
            }]
        }]
    }],
    update_mask="menus"
)

print(menus["menus"])
```
</Tab>
<Tab value="curl">
```bash
curl https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/gmb-food-menus \
  -H "Authorization: Bearer $ZERNIO_API_KEY"

curl -X PUT https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/gmb-food-menus \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "menus": [{
      "labels": [{"displayName": "Lunch Menu", "languageCode": "en"}],
      "sections": [{
        "labels": [{"displayName": "Appetizers"}],
        "items": [{
          "labels": [{"displayName": "Caesar Salad", "description": "Romaine, parmesan, croutons"}],
          "attributes": {
            "price": {"currencyCode": "USD", "units": "12"},
            "dietaryRestriction": ["VEGETARIAN"]
          }
        }]
      }]
    }],
    "updateMask": "menus"
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
  "name": "accounts/123456789/locations/12345678901234567890/foodMenus",
  "menus": [
    {
      "labels": [{ "displayName": "Lunch Menu", "languageCode": "en" }],
      "sections": [
        {
          "labels": [{ "displayName": "Appetizers" }],
          "items": [
            {
              "labels": [{ "displayName": "Caesar Salad", "description": "Romaine, parmesan, croutons" }],
              "attributes": {
                "price": { "currencyCode": "USD", "units": "12" },
                "dietaryRestriction": ["VEGETARIAN"]
              }
            }
          ]
        }
      ]
    }
  ]
}
```

Response (`200`) of the update: the same body, with `menus` as Google stored it.

## If it fails

Both endpoints proxy Google, so a `400` on `PUT /gmb-services` means Google rejected the `serviceItems` list and its own message comes back in the envelope:

```json
{
  "error": "Request contains an invalid argument.",
  "code": "gbp_bad_request"
}
```

Read the location's categories with [Location details](/platforms/google-business/business-profile#location-details), and send a `freeFormServiceItem` when Google's catalog has no `serviceTypeId` for what the business offers. On the food-menu calls a `400` means the account is not a Google Business Profile account or has no location selected; menus themselves exist only on locations Google gives menu support to. A `403` on either surface means the Google login has no permission on this location, and a `401` with code `token_invalid` means Google revoked the token, so reconnect the account. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Services](/google-business/get-google-business-services) and [Food menus](/google-business/get-google-business-food-menus): the full schemas.
- [Business Profile Management](/platforms/google-business/business-profile): verification, hours, photos, attributes and action links.
- [Multi-Location Posting](/platforms/google-business/multi-location): the `locationId` these endpoints accept.

---
