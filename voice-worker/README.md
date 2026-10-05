# voice-worker — the AI receptionist's live half

The GO AI EZ application (`app/`) is the receptionist's **brain**: it decides whether a call may be answered, what the
receptionist may say, which prices are confirmed, and it keeps the record. This directory is the **voice worker**: it holds the
live audio and talks to the brain over the signed voice brain API (`/api/voice/v1`). Plan:
`.agents/supervisor/PLAN-AI-RECEPTIONIST-2026-10-05.md`.

```
Caller → Infobip number → Infobip SIP trunk → LiveKit Cloud SIP → this worker (on its own server)
       → speech-to-text → language model → price guard → text-to-speech
       → signed HTTPS → the brain (/api/voice/v1)
```

## What is here today (wave 4a)

Only the parts that need no vendor, in the Python standard library so they test anywhere:

| file | what it does |
| :-- | :-- |
| `goaiez_voice/brain.py` | the signed client: `X-Voice-Timestamp` + `X-Voice-Signature` = HMAC-SHA256 of `"<ts>.<body>"` |
| `goaiez_voice/guard.py` | the price guard: no amount of money is spoken unless the price tool gave it on this call |
| `goaiez_voice/outbox.py` | requests the brain did not acknowledge are kept in SQLite and sent again, in order |
| `goaiez_voice/fallback.py` | what a caller hears when the receptionist will not answer: the announcement, then "leave a message" |

The LiveKit pipeline that uses them is added on the voice server, where the speech and language vendors' keys live and
nowhere else (owner ruling D-9). The application never holds those keys.

## Tests

```
cd voice-worker
python3 -m unittest discover -s tests -t . -v
```

Python 3.9 or later. `tests/test_brain.py` holds a signature vector computed with PHP's `hash_hmac`, so the two sides cannot
drift apart; `tests/test_fallback.py` holds the announcement to the Laravel side's sentence when run inside this repository.

## Settings the worker reads (on the voice server)

| name | what it is |
| :-- | :-- |
| `BRAIN_URL` | the application's base URL, e.g. `https://anti.goaiez.com` |
| `VOICE_WORKER_SECRET` | the same value as the application's `voice_worker_secret` credential |
| `OUTBOX_PATH` | where the SQLite outbox lives |
