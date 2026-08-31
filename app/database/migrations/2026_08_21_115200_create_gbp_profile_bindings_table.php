<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform index: Zernio profile id → the business whose flow made it.
 *
 * WHY THIS TABLE EXISTS, AND WHY 6779(a) REFUSED ONE LIKE IT. The previous lane
 * considered a profile→business index and refused it, in these words: *"it is
 * what a webhook-originated bind needs, and that bind is wrong for a reason a
 * table cannot fix"*. That reason is 6764: an `account.connected` payload cannot
 * name a **location**, a profile is a tenant rather than a location, and a
 * business with three locations connecting has three `pending` rows on one
 * profile. So no table can make a profile sufficient to *write* a binding.
 *
 * ⛔ **THIS TABLE DOES NOT WRITE BINDINGS AND NOTHING MAY MAKE IT.** It answers
 * a strictly weaker question — *which business started the flow that created
 * this profile?* — for a **report**, where business-level attribution is the
 * whole of what is being asked. 6779(d) says so itself: detecting an abandoned
 * connect flow *"needs the profile→business mapping of (a)"*. The refusal was
 * about binding; this is reconciliation, and the specificity 6764 demands of a
 * write is not demanded of a count.
 *
 * WHY IT CANNOT BE DERIVED FROM WHAT WE ALREADY HAVE. `gbp_connections` holds
 * `provider_profile_ref` beside `business_id` and is RLS-`ENABLE`+`FORCE`d, so a
 * platform sweep with no tenant reads zero rows from it (4732) — and the
 * business whose flow was abandoned cannot be enumerated from anywhere else
 * either: `businesses` is FORCE'd too, and `gbp_account_bindings` and
 * `zernio_account_days` only ever name a business that already completed. The
 * mapping has to be recorded at `begin()`, outside the boundary, or it is
 * unaskable afterwards — the same argument 4880 makes for `revocation_owed_at`.
 *
 * NO TENANT AND NO ROW-LEVEL SECURITY, on `gbp_account_bindings`' precedent: a
 * reader outside every tenancy is the entire point, and a policy admitting NULL
 * would admit every row. The model joins `TenancyTest`'s allowlist with its
 * reasoning there, and `GbpTest` holds `GbpConnections` as its only writer.
 *
 * ⚠️ NO FOREIGN KEY, for 4730's reason and one sharper. A statutory erasure
 * destroys the business row; **it does not reach Zernio**, who keep the profile
 * and keep charging for whatever is connected under it. A cascade here would
 * destroy the one record able to say whose abandoned flow we are still paying
 * for, at the exact moment that record starts to matter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gbp_profile_bindings', function (Blueprint $table): void {
            $table->id();

            // Unique because a Zernio profile belongs to exactly one business by
            // construction: `GbpConnections::profileNameFor()` derives the name
            // from the business id and the vendor's `name` filter is
            // exact-match, so two businesses cannot converge on one profile.
            // The uniqueness is what makes the attribution safe to render — a
            // second row for the same profile would mean an orphan could be
            // attributed to whichever row was read first, and an operator acting
            // on that would go looking for a stranger's connection inside a
            // customer's account.
            $table->string('profile_ref')->unique();
            $table->unsignedBigInteger('business_id');
            $table->timestamps();

            $table->index('business_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gbp_profile_bindings');
    }
};
