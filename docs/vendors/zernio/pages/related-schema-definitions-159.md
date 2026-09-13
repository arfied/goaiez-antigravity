# Related Schema Definitions

## YouTubeVideoRetentionResponse

### Properties

- **success** `boolean`: No description
- **accountId** `string`: The Zernio account ID for the YouTube account
- **videoId** `string`: The YouTube video ID
- **title** `string,null`: Video title
- **publishedAt** `string,null`: When the video was published on YouTube
- **durationSeconds** `integer,null`: Video length in seconds (from YouTube contentDetails.duration)
- **dateRange** `object`: 
  - **startDate** `string`: 
  - **endDate** `string`: 
- **provisionalSince** `string`: Present only when the range reaches into YouTube's ~3-day processing window: the first date whose numbers are provisional and may still be revised by YouTube.
- **retentionCurve** `array`: Up to 100 points covering the video timeline, aggregated over the date range. Can be empty when YouTube has no retention data for the video in the given range.
- **note** `string`: Present only when the curve is empty, explaining why
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
