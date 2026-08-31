<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Whose mobile this is, for the owner channel — the operational counterpart of
 * `owner_notification_consents` (10540), one row per business.
 *
 * ⛔ **DELIBERATELY NOT TENANT-OWNED, FOR `PhoneNumber`'s REASON EXACTLY.**
 * `PlatformTexter::sendToOwner()` needs to know a business's own notify number
 * from inside that business's tenancy, which an ordinary global scope would
 * happily answer. `App\Services\Sms\InboundMessages::handle()` needs the
 * OPPOSITE query — **an inbound text arrives with no tenant, and identifying
 * whether the sender is a known owner is exactly the reverse lookup
 * `TenantNumbers::tenantFor()` performs for `phone_numbers`, one table over.**
 * A tenant-scoped model answers that query with zero rows every time, which is
 * indistinguishable from "not an owner" and would silently disable the one
 * thing phase 3 of this slice exists to build.
 *
 * ⚠️ **THE SPLIT FROM `owner_notification_consents` IS `customers.sms_consent`
 * VERSUS `consent_records`, ONE MORE TIME.** That table is the append-only
 * evidence of what was shown and agreed to; this is the mutable, current
 * answer to "does this business have a notify number, and is it stopped" —
 * written by `App\Services\Consent\OwnerConsentService`, the only file
 * permitted to touch it.
 *
 * RLS `ENABLE`+`FORCE`d with `USING (true)`, `PhoneNumber`'s posture and for
 * the same reason: the platform-scoped readers — the inbound-identification
 * path above all — have no tenant to compare against, so a predicate here
 * would refuse the one query the table exists to answer. What replaces the
 * scope is `OwnerConsentService` being the only writer, held there by an
 * `ArchitectureTest` lint (`tests/Feature/Architecture/OwnerChannelTest.php`),
 * and the row carrying nothing but a business id, a number and two
 * timestamps — no name, no message text, nothing about a person beyond the
 * one phone number they consented to be texted at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_notify_numbers', function (Blueprint $table): void {
            $table->id();

            // One notify number per business — see this migration's docblock
            // on why the phone belongs on the business rather than the user.
            $table->foreignId('business_id')->unique()->constrained('businesses')->cascadeOnDelete();

            // Normalised E.164, via App\Support\Identifier::normalise() — the
            // same normal form every other suppression and send check in this
            // codebase compares against.
            $table->string('e164');

            // Set on an inbound STOP identified as this business's own owner
            // (App\Services\Sms\InboundMessages), cleared on a fresh consent
            // capture or an inbound START. Null means sendable.
            $table->timestamp('stopped_at')->nullable();

            $table->timestamps();

            $table->index('e164');
        });

        DB::statement('ALTER TABLE owner_notify_numbers ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE owner_notify_numbers FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY platform_and_tenant_readable ON owner_notify_numbers
                FOR ALL USING (true) WITH CHECK (true)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_notify_numbers');
    }
};
