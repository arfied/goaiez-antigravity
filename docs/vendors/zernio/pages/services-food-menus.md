# Services & Food Menus

Read and replace the service list of a Google Business Profile location, and the food menus of a restaurant or cafe.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page the services a Google Business Profile (`googlebusiness`) location offers, and the menus of a food business, come from the API. You need a connected Google Business Profile account (`accountId`) whose location is verified ([verification](/platforms/google-business/business-profile#verification)).

## Services

`GET /v1/accounts/{accountId}/gmb-services` returns the services the location offers and `PUT` replaces the whole list ([Services](/google-business/get-google-business-services)). Google's API has no per-item update, so send every service on each `PUT`. A service is either structured (a `serviceTypeId` from Google's catalog) or free-form (a `category` and a `label`), with an optional `price`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: services } = await zernio.gmbservices.getGoogleBusinessServices({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
});

await zernio.gmbservices.updateGoogleBusinessServices({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' },
  body: {
    serviceItems: [
      ...services.services,
      {
        freeFormServiceItem: {
          category: 'categories/gcid:plumber',
          label: { displayName: 'Pipe Repair', description: 'Emergency and scheduled pipe repair' }
        },
        price: { currencyCode: 'USD', units: '150' }
      }
    ]
  }
});
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

services = client.gmb_services.get_google_business_services(
    account_id="66b2e19d8c3f5a7e9d0b1c2d"
)

client.gmb_services.update_google_business_services(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    service_items=[
        *services["services"],
        {
            "freeFormServiceItem": {
                "category": "categories/gcid:plumber",
                "label": {"displayName": "Pipe Repair", "description": "Emergency and scheduled pipe repair"}
            },
            "price": {"currencyCode": "USD", "units": "150"}
        }
    ]
)
```
</Tab>
<Tab value="curl">
```bash
curl https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/gmb-services \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
