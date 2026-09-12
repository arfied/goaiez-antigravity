# Related Schema Definitions

## InstagramDemographicsResponse

### Properties

- **success** `boolean`: No description
- **accountId** `string`: The Zernio SocialAccount ID
- **platform** `string`: No description
- **metric** `string`: No description - one of: follower_demographics, engaged_audience_demographics
- **timeframe** `string`: The timeframe used for demographic data - one of: this_week, this_month
- **demographics** `object`: Object keyed by breakdown dimension (age, city, country, gender)
- **note** `string`: No description

---
