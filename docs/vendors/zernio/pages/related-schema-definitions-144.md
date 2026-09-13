# Related Schema Definitions

## FacebookPostEarningsResponse

Lifetime monetization earnings for one Facebook post. Same "unit" / "currency" contract and
same unavailable-vs-zero contract as the Page-level response; there is no date range, no
metricType, and no daily "values", because the single lifetime bucket IS the total.


### Properties

- **success** `boolean`: No description
- **accountId** `string`: No description
- **postId** `string`: The platform post ID that was queried, echoed back.
- **platform** `string`: No description
- **period** `string`: Always "lifetime": the total is cumulative since publication and must not be summed
across dates or across posts.
 - one of: lifetime
- **metrics** `object`: One entry per served metric. A metric reported here with "total": 0 genuinely earned
nothing (or its Page is not enrolled, which Meta reports identically).

- **unavailableMetrics** `array`: Requested metrics Meta could not serve. Present only when at least one metric is
unavailable, and absent otherwise. Each listed metric is OMITTED from "metrics" rather than
reported as 0. The request itself still succeeds with HTTP 200.

- **dataDelay** `string`: No description

---
