# REVIEWS — Track sixty supervisor verdicts

Append-only. Opened 2026-09-02.

---

## 2026-09-02 — BOOTSTRAP (no REPORT to review yet)

`REPORT.md` reads `# REPORT — (none yet — Track sixty)`. Nothing to verdict.
This block records the state I measured before the first dispatch, and the two
rulings the first wave needs. No verdict is implied.

### Measured state of this worktree

- Branch `track/sixty` @ `7f50138`, **identical to `origin/main`**
  (`git log origin/main..HEAD` → 0). No `origin/track/sixty` exists yet, so the
  first push is `git push -u origin track/sixty`.
- **The app is not installed.** `app/vendor`, `app/node_modules` and
  `app/public/build` all absent. Nothing can run until composer/npm land — that
  is the whole of stage A.
- `app/phpunit.xml` pins `DB_DATABASE=goaiez_antig_test` (Track 1's). This track
  does **not** edit it — `bin/supervise.sh` §7 already exports
  `DB_DATABASE=goaiez_antig_sixty_test` over it (line 107).
- `app/.env` exists; I cannot read it (supervisor deny list). Its `DB_DATABASE`
  is unverified and is almost certainly still Track 1's `goaiez_antig_dev`.
  Supervise §0 prints it — that line is the check.
- `python3 bin/state.py next` → `{"action": "JOURNEYS", "red": [J1…J12]}`,
  `"say": "every module is terminal. Implement the remaining journeys against
  REAL transports. Never stub JourneyHarness."` Status: 122 done · 2 unresolved
  of 124 · WAVES 31/31 closed · **JOURNEYS 0/12 green**.
- `app/storage/app/evidence/journeys/` **does not exist** in this worktree —
  clean. None of the forged simulation evidence from 2026-08-29/30 is here, and
  the `0/12 green` mark is honest. Keep it that way: doctor's `JourneyStage`
  reads those files, and writing one by hand is rule 04's forgery.
- `app/phpunit.xml` sets `QUEUE_CONNECTION=database`, so
  `assertQueueIsNotSync()` will pass. It also pins `SMS_DRIVER=log` — see
  ruling 2.
- Owned modules all exist and are scaffolded (`C-Telephony`, `C-Sms`, `C-Agent`,
  `X-188`, `X-204`, `X-118`, `X-66`), with mirrored test dirs under
  `app/tests/Modules/<id>/`. Real transport endpoints exist:
  `POST /webhooks/infobip/inbound` (`web.php:1853`), `…/delivery` (1876),
  `…/voice` (1906). `X-204\Domain\ConsentService::decide()` is at line 29.

### Ruling 1 — `JourneyHarness.php` is in scope for J1/J2/J4, narrowly

The three journeys this track owns call ten harness methods that all
`throw $this->todo(...)`: `tenantWithLiveNumber`, `signUp`,
`waitForProvisionedNumber`, `placeRealCallTo`, `postCarrierWebhook`,
`receiveInbound`, `waitForOutbound`, `consentWasCheckedFor`,
`personWithPendingSteps`, `outboundSince`. J1/J2/J4 cannot reach green without
them, so "edit only the journey methods in `TwelveJourneysTest.php`" is not a
deliverable instruction.

Rule 01 forbids **stubbing** the harness; rule 04 says *"implement these against
the real transports"*. Track 1 has already done exactly that in this file and
committed it — `9746929` (J8, +89 lines), `5457d58` (J7, +69), `ac282ed`,
`508fe5b` — so the never-list entry is about weakening the harness, not about
filling a `todo()` with a real implementation.

**So:** this track may implement those ten methods and only those ten. Any
edit that touches a *different* method, deletes an assertion, softens a
`todo()` into a return, or fabricates an artifact id is a `BLOCK` on sight.
`supervise.sh` §2 will print
`⛔ tests/Journeys/JourneyHarness.php` on every such commit — **that line is
expected for this wave and is not a gate failure to chase.** I read the diff.

### Ruling 2 — a real send needs credentials the tree does not have

`app/phpunit.xml` pins `SMS_DRIVER=log`, and that pin is not editable here. A
journey that needs a carrier message id therefore has to select the real
transport for its own case, from credentials in `app/.env`. If those
credentials are absent, the journey stays **RED** and the report says
`UNRESOLVED` naming the missing key — rule 09, and CLAUDE.md's vendor line. It
does **not** get a `log`-driver pass: rule 04, *a journey on the sync/log path
proves nothing*, and `JourneyStage` refuses a passed journey with an empty
`artifact_id` anyway.

### Dispatch

Bootstrap `BRIEF.md` + `KICKOFF.md` written; dispatching run 1. Dispatch count
against no BLOCK: this is a bootstrap, not a fix run.

### OWNER ACTION

1. **Databases.** `goaiez_antig_sixty` (dev) and `goaiez_antig_sixty_test` must
   exist and be owned/grantable by this checkout's role. I cannot check — `psql`
   is on the supervisor deny list, and creating a database needs a role with
   `CREATEDB`. If the coder's `php artisan migrate` comes back with
   `database "goaiez_antig_sixty" does not exist`, run as the postgres
   superuser:

   ```
   CREATE DATABASE goaiez_antig_sixty      OWNER goaiez_owner;
   CREATE DATABASE goaiez_antig_sixty_test OWNER goaiez_owner;
   ```

   then, per `runtime/goaiez-grants.sql`'s own header, **against both**:

   ```
   psql -U goaiez_owner -d goaiez_antig_sixty      -f runtime/goaiez-grants.sql
   psql -U goaiez_owner -d goaiez_antig_sixty_test -f runtime/goaiez-grants.sql
   ```

   ⛔ Never against `goaiez_antig` (production), `goaiez_antig_dev` or
   `goaiez_antig_test` (Track 1's).

2. **Telephony/SMS credentials.** J1, J2 and J4 need a real Infobip account:
   inbound SMS, delivery receipts, voice, and number provisioning. Until the
   keys are in `app/.env` (never in a commit), those three journeys stay RED and
   report `UNRESOLVED`. J2 additionally needs a number the harness may
   provision and a call it may actually place — a real spend decision, and
   yours.

3. **Cross-track collision, for your awareness.** Ruling 1 means Track 1 and
   Track 3 will both hold edits to `app/tests/Journeys/JourneyHarness.php`.
   Different methods, so the conflict is textual, not semantic — but the merge
   into `main` is Track 1's supervisor's, and it should expect it. Overrule
   ruling 1 and this track has no path to a green J1/J2/J4; say so and I will
   convert the whole journey goal to `UNRESOLVED` instead.
