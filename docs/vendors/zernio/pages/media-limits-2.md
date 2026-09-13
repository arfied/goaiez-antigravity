# Media & Limits

WhatsApp media formats and size limits, what the API does not expose, and the Meta error codes you will meet, each with its fix.

Every limit WhatsApp puts on a send, and the code it returns when you cross one. Attachment limits come first: each URL must be public with no authentication and return the file itself, not an HTML page ([media uploads](/guides/media-uploads)).

## Media requirements

### Images

| Property | Requirement |
|----------|-------------|
| Formats | JPEG, PNG |
| Max file size | 5 MB |

### Videos

| Property | Requirement |
|----------|-------------|
| Formats | MP4, 3GPP |
| Max file size | 16 MB |
| Codec | H.264 video, AAC audio |

### Documents

| Property | Requirement |
|----------|-------------|
| Formats | PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT |
| Max file size | 100 MB |

### Audio

| Property | Requirement |
|----------|-------------|
| Formats | MP3, OGG (Opus codec), AMR, AAC |
| Max file size | 16 MB |

A voice note is OGG with the Opus codec, mono, sent with `voiceNote: true` ([WhatsApp inbox](/platforms/whatsapp/inbox#direct-messages)).

## What you cannot do

WhatsApp's API does not allow:

- Free-form messages outside the 24-hour customer service window (send an approved template)
- Messages to numbers that are not on WhatsApp
- Personal WhatsApp accounts (a WhatsApp Business Account is required)
- More than 250 unique contacts per day on a new number (Meta's starting tier, raised with usage)
- Scheduling a single message (schedule a broadcast instead)
- Per-message analytics beyond delivery status

## Common errors

| Error | Cause | Fix |
|-------|-------|-----|
| "Template not found" (132001) | The template name or language code does not match | Use the exact name and language code the template was created with (`en` is not `en_US`) |
| "Invalid phone number" (131021) | The recipient number is malformed or not on WhatsApp | Use E.164 with the country code, for example `+13105551234` |
| "Re-engagement message" (131047) | The 24-hour customer service window has closed | Send an approved template to reopen the conversation |
| "Message undeliverable" (131026) | The recipient cannot receive this message: the number is not on WhatsApp or runs an outdated app, or Meta is holding back marketing messages to them | Check the number first; if your other messages to the contact deliver, send the content as a utility template instead of a marketing one |
| "Too many messages sent to this recipient" (131056) | More than about 10 messages a minute to one recipient | Pace sends per recipient; other recipients are unaffected |
| "Media download failed" (131052) | WhatsApp could not fetch the media URL | Serve the file from a public URL with no authentication |
| "Not in allowed list" (131030) | The number is not in Meta's test recipient list | Add it to your test recipients in Meta Business Suite |
| "Account locked" (131031) | Meta suspended the WhatsApp Business Account | Contact Meta support |
| `(#200) You do not have the necessary permission` on every send | The number has a two-step PIN that the connect flow could not register (error 133005) | [Register the number with its PIN](/platforms/whatsapp/phone-numbers#verification-with-meta) |

A failed send returns the error on the call; a message that was accepted and then failed to deliver arrives as `message.failed` with the same Cloud API code in its `error` object. The envelope every error shares is in [error handling](/guides/error-handling).

## Related

- [WhatsApp inbox](/platforms/whatsapp/inbox): what each attachment type looks like when sent.
- [Media uploads](/guides/media-uploads): host files on Zernio instead of your own URL.
- [Templates](/platforms/whatsapp/templates): the fix for 131047 and 132001.
- [WhatsApp phone numbers](/platforms/whatsapp/phone-numbers): the liveness check and the PIN registration.
- [Pricing & costs](/platforms/whatsapp/pricing): who bills what.

---
