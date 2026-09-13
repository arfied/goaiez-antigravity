# Related Schema Definitions

## DiscordScheduledEvent

Discord guild scheduled event. Returned by /v1/discord/guilds/{guildId}/events endpoints.
Fields below are the subset Zernio consumes. Discord may return more (e.g. creator,
image hash) which we pass through verbatim.


### Properties

- **id** `string`: Event snowflake ID
- **guild_id** `string`: No description
- **channel_id** `string,null`: Voice/stage channel ID; null for external events.
- **creator_id** `string,null`: No description
- **name** `string`: No description
- **description** `string,null`: No description
- **scheduled_start_time** `string`: No description
- **scheduled_end_time** `string,null`: Required for external events; optional for voice/stage.
- **privacy_level** `integer`: Always 2 (GUILD_ONLY). Discord deprecated PUBLIC events. - one of: 2
- **status** `integer`: 1=SCHEDULED, 2=ACTIVE, 3=COMPLETED, 4=CANCELED - one of: 1, 2, 3, 4
- **entity_type** `integer`: 1=STAGE_INSTANCE, 2=VOICE, 3=EXTERNAL - one of: 1, 2, 3
- **entity_id** `string,null`: No description
- **entity_metadata** `object,null`: No description
- **user_count** `integer`: Number of members who RSVP'd. Only present when withUserCount=true on list.
- **image** `string,null`: Cover image hash; build URL via cdn.discordapp.com.

---
