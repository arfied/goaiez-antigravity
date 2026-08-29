---
name: goaiez-journey
description: Implement a journey end to end against real transports. Use when a wave unlocks a journey or bin/state.py returns JOURNEYS.
---

# IMPLEMENT A JOURNEY

**A module passing its own seven gates says nothing about whether a plumber can
be paid.** Journeys are the only checks that cross a module seam.

```bash
php artisan test --group=journeys
python3 bin/state.py journey <Jn> green
```

## THE TWELVE

| J1 | a missed call becomes a consented text back — ⭐ **the whole product in sixty seconds** |
| :--- | :--- |
| J2 | two fields at signup put a live agent on a real number |
| J3 | a quote comes from the pricebook or does not come at all |
| J4 | STOP halts every pending step for that person |
| J5 | a migration of five hundred jobs sends nothing |
| J6 | cancel is one tap with nothing in between |
| J7 | an agency client never sees cost or margin |
| J8 | a deliberately corrupted backup fails the restore |
| J9 | an invoice reaches a real charge id |
| J10 | a completed job asks for a review once inside the cadence |
| J11 | a published site carries all seven |
| J12 | an overdue invoice is chased by reason, resolution before any stop |

## ⛔⛔⛔ NEVER STUB THE HARNESS

`tests/Journeys/JourneyHarness.php` — **every method throws on purpose.**

Returning plausible fixtures makes **all twelve pass while touching no carrier,
no gateway, no queue and no database.** A green journey suite that proves nothing
is worse here than anywhere else, precisely because these are the only
seam-crossing checks in the system.

**Implement them against the real transports.**

## ⛔ AND NOT ON THE `sync` DRIVER

Laravel runs queued jobs synchronously in tests, so a journey on `sync` **would
pass with no worker running.** The harness refuses it and is right to. Run a real
worker — with `--stop-when-empty`, because a bare `queue:work` never returns and
everything chained after it with `&&` silently never ran.

## EVIDENCE

Each journey writes `storage/app/evidence/journeys/<slug>.json`, which the
`journey` stage reads.

⛔ **A journey that "passed" with no external artifact id in its evidence has not
passed.** And ⛔ **never write that file by hand** — see rule 04.

## J4 AND J5 ARE THE COMPLIANCE PAIR

**STOP must halt work already in flight**, not merely stop new work — so the
fixture needs steps *already queued* before STOP arrives. And **a migration of
five hundred jobs sends nothing**: an import is not a consent record, and the
recorded basis is what decides, per person, per channel.
