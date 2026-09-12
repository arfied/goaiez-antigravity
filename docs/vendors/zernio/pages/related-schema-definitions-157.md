# Related Schema Definitions

## YouTubeDailyViewsResponse

### Properties

- **success** `boolean`: No description
- **videoId** `string`: The YouTube video ID
- **durationSeconds** `integer,null`: Video length in seconds (from YouTube contentDetails.duration)
- **dateRange** `object`: 
  - **startDate** `string`: 
  - **endDate** `string`: 
- **provisionalSince** `string`: Present only when the range reaches into YouTube's ~3-day processing window: the first date whose numbers are provisional and may still be revised by YouTube.
- **totalViews** `integer`: Sum of views across all days in the range
- **dailyViews** `array`: No description
- **lastSyncedAt** `string,null`: When the data was last synced from YouTube
- **scopeStatus** `object`: 
  - **hasAnalyticsScope** `boolean`: 

## YouTubeScopeMissingResponse

### Properties

- **success** `boolean`: No description
- **error** `string`: No description
- **code** `string`: No description
- **scopeStatus** `object`: 
  - **hasAnalyticsScope** `boolean`: 
  - **requiresReauthorization** `boolean`: 
  - **reauthorizeUrl** `string`: URL to redirect user for reauthorization

---
