# Next page: pass history["nextCursor"] as `before`
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/calls?limit=50" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "calls": [
    {
      "_id": "66e5f6a7b8c9d0e1f2a3b4c5",
      "channel": "pstn",
      "direction": "outbound",
      "from": "+14155550100",
      "to": "+13105551234",
      "status": "ended",
      "endReason": "hangup",
      "durationSeconds": 184,
      "recordingEnabled": true,
      "recordingUrl": "https://...",
      "lastTranscriptSnippet": null,
      "contactId": null,
      "contactName": null,
      "conversationId": "66c3d2ae7b4f6c8d0e1f2a3b",
      "startedAt": "2027-01-01T12:00:00Z",
      "endedAt": "2027-01-01T12:03:04Z"
    }
  ],
  "nextCursor": "2027-01-01T12:00:00.000Z"
}
```

Pass `nextCursor` as `before` for the next page; it is `null` on the last page. List rows omit `transcript`; `lastTranscriptSnippet` is the preview.

## Fetch a call and its recording

Call `GET /v1/calls/{id}` for one call on either channel. It returns the full record, including `transcript` segments when transcription was on.

```bash
curl "https://zernio.com/api/v1/calls/66e5f6a7b8c9d0e1f2a3b4c5" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "call": {
    "_id": "66e5f6a7b8c9d0e1f2a3b4c5",
    "channel": "pstn",
    "direction": "outbound",
    "status": "ended",
    "durationSeconds": 184,
    "recordingEnabled": true,
    "transcriptionEnabled": true,
    "transcript": [
      { "text": "Hi, this is Acme calling about your order.", "confidence": 0.97, "at": "2027-01-01T12:00:06Z" }
    ],
    "billing": { "telnyxSeconds": 184, "billableCostUSD": 0.135 }
  }
}
```

`recordingUrl` on a call record is signed and expires about 10 minutes after signing. Call `GET /v1/calls/{id}/recording` for a fresh one: by default it answers `302` to a playable MP3 URL; pass `as=json` to get `{ "url": "..." }` instead.

```bash
curl "https://zernio.com/api/v1/calls/66e5f6a7b8c9d0e1f2a3b4c5/recording?as=json" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "url": "https://recordings.example.com/66e5f6a7b8c9d0e1f2a3b4c5.mp3?token=..."
}
```

A recording exists only when [recording was enabled](/platforms/voice/setup#optional-inbound-features) on the number or the call at call time.

## If it fails

A `404` on `GET /v1/calls/{id}/recording` means the call does not exist under your key or has no recording:

```json
{
  "error": "Call not found, or no recording is available for this call",
  "type": "not_found"
}
```

Check `recordingEnabled` on the call record; recording has to be on before the call starts. A `502` means the recording provider lookup failed; retry. Every error uses the envelope in [error handling](/guides/error-handling).

## Related

- [List all calls](/calls/list-calls), [Get a call](/calls/get-call) and [Get a call recording](/calls/get-call-recording): every field and filter.
- [Setup](/platforms/voice/setup#optional-inbound-features): turn on recording and transcription.
- [Call webhooks](/webhooks/calls): `call.ended` pushes each finished call to you instead of polling.
- [WhatsApp Calling](/platforms/whatsapp/calling): the other channel in this feed.

---
