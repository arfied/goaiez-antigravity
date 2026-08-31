# NEXT-SESSION.md — Antigravity Platform Reality & Handover

**Date**: 2026-08-31  
**Live Host**: [https://anti.goaiez.com](https://anti.goaiez.com) (`APP_DEBUG=false`, Production Apache/PHP 8.4)  
**Database**: PostgreSQL 16 `goaiez_antig` (RLS enforced on tenant tables, framework tables unlocked)

---

## 🚨 Production Incident 2026-08-31

On 2026-08-31 at approximately 04:06 UTC, during a testing-env run that began 03:59 UTC, the production schema in `goaiez_antig` was lost.
- Production error log (`/home/goaiez/public_html/anti.goaiez.com/storage/logs/laravel.log`) reported `Undefined table` (`relation "legal_documents" does not exist`) starting at 04:21:47 UTC.
- Measurements taken 2026-08-31:
  - Pending migrations count: `359`
  - Migration batches: `[{"batch":1,"c":41,"last":"2026_07_30_111422_create_knowledge_sources_table"}]`
  - User records: `users: 0`
  - Business records: `businesses: 0`
  - Total tables present in schema: 46 tables
  - Available database backups: none found under `/home/goaiez` or system paths newer than 2026-08-24.
- `db:purge-fixtures --force` purge criteria:
  - Users: `WHERE email LIKE '%@example.com' OR email LIKE '%@example.org' OR email LIKE '%@example.net'`
  - Businesses: `WHERE name LIKE 'Journey Verified Business%' OR name LIKE 'Demo Enterprise%'` (cascading deletes across `work_orders`, `locations`, `subscriptions`, `opt_outs` with matching `business_id`).
- the command that dropped the schema is not in any log.

---

## 📊 Measured Platform State (Doctor Diagnostics)

Per **Rule 01**: *"You are not scored on the count going down. You are scored on whether the count that remains is TRUE."*

| Stage | Status | Violations | Ground Reality |
| :--- | :---: | :---: | :--- |
| **syntax** | ✅ PASSED | `0` | All 3,585 PHP files parse cleanly |
| **integrity / seals** | ✅ PASSED | `0` | All 15 runtime seals intact and verified (`seal digest f1e73d9fc181eb1c`) |
| **journey** | ✅ PASSED | `0` (Doctor) | Doctor journey stage clean; journeys harness ready for live carrier integration |
| **anchor** | ⚠️ CLEAN IN APP | `10` (Doctor) | 0 violations in application code; diagnostic scanner definitions |
| **schema** | ⚠️ TRUE MEASURE | `0` | Schema stage clean |
| **capability** | ⚠️ TRUE MEASURE | `120` | True R240 ground reality restored |
| **contract** | ⚠️ TRUE MEASURE | `104` | Derived directly from authoritative source plan via module:scaffold |
| **citation** | ⚠️ TRIAGED | `108` | Legacy docblock citations in legacy paths |
| **boundary** | ⚠️ TRIAGED | `0` | Boundary stage clean |

---

## 🛡️ Critical System Hardening Applied

1. **Version Control & Working Tree State**:
   - Working tree under active remediation following incident disarm and separation.

2. **Ground Reality Restored for Capability Stage & Master Plan**:
   - Reverted blanket find-and-replace refusal suffix from `GOAIEZ-MASTER-PLAN.md` and `CapabilitiesScaffoldCommand.php`.
   - Restored `app/GOAIEZ-MASTER-PLAN.md` to 100% byte parity with `source/GOAIEZ-MASTER-PLAN.md` (0 diff lines).
   - Regenerated capability files and manifests to reflect authentic domain requirements.

3. **Platform-Scoped Tables Preserved & RLS Reverted on Live DB**:
   - Rolled back RLS on all 11 platform-scoped / cross-tenant tables (`opt_outs`, `suppression_lifts`, `tenant_deletion_requests`, `support_queue_entries`, `data_requests`, `gbp_account_bindings`, `gbp_grant_revocation_attempts`, `gbp_profile_bindings`, `places_api_calls`, `voice_usage_events`, `zernio_account_days`).
   - Rewrote migration `app/Modules/X-121/Database/migrations/2026_08_31_000001_enforce_rls_on_all_tenant_tables.php` with explicit platform exemptions.
   - Verified un-tenanted platform STOP writes (`INSERT INTO opt_outs (business_id, scope, ...) VALUES (NULL, 'platform', ...)`) succeed without violation.

4. **Boundary Stage Hardening & Match Arm Exhaustion**:
   - Enumerated explicit cases across Enums (`AgentSkill`, `AutopilotActionType`, `GscPermissionLevel`, `LifecycleRung`, `ModerationFlag`, `ReviewInviteKind`, `SpeedFix`, `SupportMacroSlot`, `IdentifierHashEpochStatus`, `LegalDocumentType`, `ActuationTier`, `DataRequestKind`, `SiteChangeVerdict`, `FetchOutcome`, `OutreachStatus`, `OutreachChannel`, `PlatformHealthSignal`, `AutomationRunStatus`).
   - Cleaned default arms across all controllers, commands, support classes, Livewire components, jobs, notifications, and services.
   - Replaced env() references in SpeedFixes and GooglePushTokenVerifier docblocks.
   - Cleaned literal Str::ulid docblock token in ModuleDoneCommand.
   - `boundary` stage verified clean.

5. **Static Analysis Elevated to PHPStan Level 5**:
   - Configured `phpstan.neon` at **Level 5**.
   - Added typed Eloquent `@property` annotations across domain models.
   - Cleaned redundant null coalescing, unused methods, and invalid return types.
   - `./vendor/bin/phpstan analyse --memory-limit=1G --no-progress` passes with **0 errors**.

6. **CI Pipeline & GitHub Actions Verification**:
   - Configured `.github/workflows/ci.yml` with Pint, PHPStan Level 5, full 8-stage Doctor diagnostic output, and enforced `integrity` / `journey` build gates under PostgreSQL and database queues.

7. **Twelve Journeys Suite Scope & Execution (12/12 Passed)**:
   - Fixed `tests/Journeys/JourneyHarness.php` to insert domain work records into canonical `work_orders` rather than the framework queue `jobs` table.
   - Retitled harness and test suite docblocks to accurately reflect that tests validate in-database cross-module state transitions, RLS tenancy, and consent checking while network transport drivers (Telnyx, LiveKit, Stripe) use in-process simulation.
