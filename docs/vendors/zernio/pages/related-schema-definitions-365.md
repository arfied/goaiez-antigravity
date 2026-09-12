# Related Schema Definitions

## QueueSlotsResponse

Single queue response (default behavior)

### Properties

- **exists** `boolean`: No description
- **schedule**: No description
- **nextSlots** `array`: No description

## QueueSchedule

### Properties

- **_id** `string`: Unique queue identifier
- **profileId** `string`: Profile ID this queue belongs to
- **name** `string`: Queue name (e.g., "Morning Posts", "Evening Content")
- **timezone** `string`: IANA timezone (e.g., America/New_York)
- **slots** `array`: No description
- **active** `boolean`: Whether the queue is active
- **isDefault** `boolean`: Whether this is the default queue for the profile (used when no queueId specified)
- **createdAt** `string`: No description
- **updatedAt** `string`: No description

## QueueUpdateResponse

### Properties

- **success** `boolean`: No description
- **schedule**: No description
- **nextSlots** `array`: No description
- **reshuffledCount** `integer`: No description
- **skippedDailyLimit** `integer`: No description
- **isNewQueue** `boolean`: No description

## QueueDeleteResponse

### Properties

- **success** `boolean`: No description
- **deleted** `boolean`: No description
- **deletedCount** `integer`: No description
- **message** `string`: No description

---
