# BRIEF — from the supervisor (Track 3 · `track/sixty`)

updated: 2026-09-02 12:05
push: ⛔ BLOCKED. Nothing leaves this checkout until this worktree's
      `REVIEWS.md` records `PASS` or `PASS-WITH-NOTES` for the bootstrap wave.
      The first push, when cleared, is `git push -u origin track/sixty` — this
      track never pushes to `main`. Track 1 merges.
report: at the end of stage A, and again at the end of stage B or on any stop.

⚠️ **This file replaces the Track 1 brief that was in this worktree.** Everything
it said about waves 13–30, X-192, `goaiez_antig_dev` and roster modules belongs
to another track and is void here. Read `CLAUDE.md` §"TRACK 3 — sixty".

## What this track is

Branch `track/sixty` at `7f50138` — identical to `origin/main`, nothing local.
`state.py next` returns `JOURNEYS`: every module is terminal, and the work left
in the whole programme is the twelve journeys against real transports.

**You own three of them, and nothing else:**

| J1 | `a_missed_call_becomes_a_consented_text_back` | evidence slug `missed-call-textback` |
| :--- | :--- | :--- |
| J2 | `two_fields_at_signup_put_a_live_agent_on_a_real_number` | `day-one` |
| J4 | `stop_halts_every_pending_step_for_that_person` | `inbound-consent` |

Modules you own: `C-Telephony`, `C-Sms`, `C-Agent`, `X-188`, `X-204`, `X-118`,
`X-66`. `X-121` is the spine — **read it, never edit it**.

## Scope — where your edits may land

Allowed:

- `app/app/Modules/<id>/**` for the seven ids above
- `app/tests/Modules/<id>/**` for the same seven
- the **three owned journey methods** in `app/tests/Journeys/TwelveJourneysTest.php`
- the **ten** `JourneyHarness.php` methods J1/J2/J4 need — see rule below
- `app/.env` (gitignored, never committed) — the `DB_DATABASE` line only

⛔ Out of scope, and a `BLOCK` if touched: any other track's module or journey ·
`resources/views` and `app/Livewire` (Track 2) · `app/app/Doctor/**` ·
`seals.json` · generated `manifest.php`/`capabilities.php` except by
regeneration · `app/phpunit.xml` (**any** line, and the `DB_` lines especially) ·
`.env.example` · `bin/state.py` · `runtime/**` · `source/**` ·
`/home/goaiez/public_html/**` · this file and `REVIEWS.md`.

## The harness ruling (REVIEWS.md 2026-09-02, ruling 1)

J1/J2/J4 call ten methods in `app/tests/Journeys/JourneyHarness.php` that all
`throw $this->todo(...)`:

```
tenantWithLiveNumber   signUp                  waitForProvisionedNumber
placeRealCallTo        postCarrierWebhook      receiveInbound
waitForOutbound        consentWasCheckedFor    personWithPendingSteps
outboundSince
```

**You may implement those ten, against the real transport, and only those ten.**
Rule 01 forbids *stubbing* the harness, not *implementing* it — rule 04 says in
so many words "implement these against the real transports". Track 1 already
did this in the same file (`9746929`, `5457d58`).

⛔ Touching any *other* method in that file, deleting an assertion, softening a
`todo()` into a `return`, or minting an `artifact_id` yourself is a `BLOCK` on
sight and I will read the whole diff for it.

ℹ️ `bash bin/supervise.sh` §2 prints `⛔ tests/Journeys/JourneyHarness.php` on
every commit that touches it. **That line is expected for this wave.** Do not
chase it, do not edit `supervise.sh` to quiet it, and say in your report that
you saw it.

## Standing orders

1. **`goaiez_antig` is PRODUCTION** (anti.goaiez.com). A test run from a
   checkout dropped its schema on 2026-08-31. `goaiez_antig_dev` and
   `goaiez_antig_test` are **Track 1's** — touch neither. This track:
   dev `goaiez_antig_sixty`, tests `goaiez_antig_sixty_test`.
   `supervise.sh` exits 2 if either configured value is production.
2. **Never edit `app/phpunit.xml`.** Its `DB_DATABASE=goaiez_antig_test` pin
   stays. `supervise.sh --tests` already exports
   `DB_DATABASE=goaiez_antig_sixty_test` over it (line 107). Any pest run you
   do by hand carries that same prefix.
3. **Commit per concern**, `feat(J1): …` / `fix(C-Sms): …` / `chore: …`.
   Uncommitted work is invisible to review.
4. **Never** `git commit --amend`, `reset`, `rebase`, or edit/delete a migration
   that has already run. Fix forward with a new migration. The post-rewrite
   hook records every rewrite into `REWRITES.log` and `supervise.sh` §2a fails
   the wave on it.
5. **Never** stash / checkout / restore / clean the supervisor's files
   (`.agents/supervisor/**`, `CLAUDE.md`, `bin/supervise.sh`). They are
   uncommitted on purpose. A stashed supervisor ledger had to be reconstructed
   by hand once — rule 10's own heading.
6. Cite nothing `php artisan why <id>` cannot resolve. 64 unresolvable
   citations already exist; the 65th is a `BLOCK`.
7. Pint only as bare `./vendor/bin/pint`, as a separate follow-up commit.
8. **GitHub Actions is out of scope** (owner, 2026-09-01). `bin/supervise.sh`
   locally is the arbiter.
9. Never run `state.py done` / `journey` / `stage` to claim a journey green.
   `JOURNEYS n/12 green` in `state.py status` is a hand mark, and all twelve
   were once marked green before a harness existed. Only the gate's output
   counts, and only I read it.
10. ⛔ **Never write `app/storage/app/evidence/journeys/*.json` by hand.** That
    directory does not exist in this worktree and its absence is honest.
    Doctor's `JourneyStage` reads those files; writing one to pass the stage is
    rule 04's forgery, the single thing this programme exists to prevent.

## Stage A — make this checkout runnable

Nothing here can run: `app/vendor`, `app/node_modules` and `app/public/build`
are all absent. In order, from the repo root:

1. `composer install --working-dir=app`
2. `npm --prefix app ci && npm --prefix app run build`
   — a missing Vite manifest makes a large suite print **zero bytes**, which
   reads as a hang. Confirm `app/public/build/manifest.json` exists before you
   run anything.
3. **Point dev at this track's database.** `app/.env` is inherited and its
   `DB_DATABASE` is almost certainly still `goaiez_antig_dev` (Track 1's).
   Set it to `goaiez_antig_sixty`. `.env` is gitignored — this is not a commit.
   Verify with `bash bin/supervise.sh` §0, which prints both values.
4. `php artisan migrate` (from `app/`), against `goaiez_antig_sixty`.
   - If it reports `database "goaiez_antig_sixty" does not exist`, **stop that
     step** — creating a database needs a superuser and is the owner's. Write
     it into `REPORT.md` under `UNRESOLVED` and carry on with what does not
     depend on it.
5. `psql -U goaiez_owner -d goaiez_antig_sixty -f runtime/goaiez-grants.sql`
   and the same against `goaiez_antig_sixty_test`. That file's own header says
   run it against **every** database including the test one; without it a new
   table is one the app role cannot write, and the failure surfaces weeks later
   as `SQLSTATE[42501]: permission denied` — which is a GRANT problem and not
   an RLS one. ⛔ Never point it at `goaiez_antig`, `goaiez_antig_dev` or
   `goaiez_antig_test`. Read-only credentials or a missing role here is again
   `UNRESOLVED`, not a workaround.
6. `bash bin/supervise.sh --tests`. **Paste the §7 line verbatim** —
   `tests N · passed N · FAILED N · errors N · result …`.

   Expected: the twelve journeys fail with
   `JOURNEY HARNESS NOT IMPLEMENTED`, and **nothing else fails**. The bootstrap
   target for the non-journey suite is `886 tests / FAILED 0`; if your run
   disagrees with that on anything other than the twelve journeys, that is a
   bootstrap defect — name each failure in the report, do not "fix" a test to
   make it green.

Then `REPORT.md`, rule-10 shape, and continue to stage B.

## Stage B — J1, on the real transport

J1 is the whole product in sixty seconds: a call is missed, a consented text
arrives **carrying the carrier's own message id**.

The transport is already wired: `POST /webhooks/infobip/inbound`
(`app/routes/web.php:1853`), `/delivery` (1876) and `/voice` (1906). The consent
decision is `App\Modules\X204\Domain\ConsentService::decide()` (line 29).
`C-Telephony` carries `CarrierProvisionAction`, `CarrierSendAction`,
`CarrierRouter`, `CarrierReceipt`; `C-Sms` carries `SmsSendAction` and
`SmsComposer`. All of that is yours.

What J1's assertions actually demand, in order:

- `assertQueueIsNotSync()` — passes already, `phpunit.xml` pins
  `QUEUE_CONNECTION=database`. Do not change it.
- `postCarrierWebhook(event: 'call.missed')` must POST the **carrier's real
  webhook body shape**, not a synthetic event you invented. Read the vendor's
  payload; a body only this repo has ever seen proves nothing.
- `waitForOutbound()` must poll for a **stored row carrying the provider's
  message id** — not a queued job, not an intent, not a `Str::ulid()`.
  Rule 04: *could you find this id in somebody else's system?*
- `consentWasCheckedFor()` must assert a real consent **decision row** exists
  for that send. Not that the recipient looked consented — that the send went
  through `ConsentService::decide()`.

⛔ **Credentials.** `app/phpunit.xml` pins `SMS_DRIVER=log` and you may not
change it, so a real send has to select the real transport for J1's own case
from keys in `app/.env`. **If those keys are not there, stop.** J1 stays RED,
and `REPORT.md` says `UNRESOLVED` naming the exact missing env keys and what
each is for. That is the correct outcome and it is worth more than a green run
— rule 09, and CLAUDE.md's vendor line. Do **not** let J1 pass on the `log`
driver, and do **not** write its evidence file.

Everything that does **not** depend on the missing keys still gets done: the
carrier webhook handler, the consent decision on the text-back path, the
outbound row with a `provider_message_id` column populated from the receipt,
and module tests under `app/tests/Modules/C-Telephony/` and `C-Sms/` proving
each of those in isolation. Ship that, commit it, and report the credential gap.

J2 and J4 wait for the next brief. Do not start them.

## Report when

Stage A ends · stage B ends · any stop condition · anything in this file turns
out to be wrong. Rule-10 shape, raw output not paraphrase — every wrong turn in
this programme came from a paraphrase.
