<?php

declare(strict_types=1);

use App\Services\Gbp\GbpConnections;
use App\Services\Tenant\TenantDeletion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The one column that survives a tenant and says we still owe them a revocation.
 *
 * ## ⚠️ WHY THIS IS A COLUMN AND NOT A QUERY (4731, 4732)
 *
 * `TenantDeletion::execute()` released the tenant's phone number and never
 * disconnected Zernio, so a deleted customer left two things behind: a monthly
 * bill for an account nobody owns, and — the half that matters — a subprocessor
 * still holding `business.manage`, read **and write**, on that former customer's
 * Google Business Profile, with no tenant record left to revoke it from.
 *
 * The obvious repair is a sweep asking *"which bindings name a business that no
 * longer exists?"*. **It cannot be written as a set query.** `businesses`,
 * `gbp_connections` and `locations` are all RLS-`ENABLE`+`FORCE`d on
 * `app.business_id`, so a platform sweep with no tenant reads zero rows from all
 * three and answers **"every binding is an orphan"** — a lint would pass and the
 * page would be a decoration (4732).
 *
 * So the fact is **recorded where it is known** rather than inferred later. The
 * deletion path has a tenant by construction: it is the only code in this
 * application that knows a specific business is about to stop existing. It
 * stamps this column inside the same transaction that destroys the account, and
 * the vendor call happens after that transaction commits. That ordering is the
 * whole design — see {@see TenantDeletion::execute()}.
 *
 * ⚠️ **A NULL HERE IS NOT "REVOKED", IT IS "NOT OWED".** A row with a null in
 * this column is an ordinary live binding for an ordinary live tenant. The
 * revoked state has no representation at all, because a revoked account has no
 * binding: {@see GbpConnections::revokeOwedGrants()} deletes
 * the row on success, which is what takes the account off `zernio:meter`'s count
 * and stops the bill (4721).
 *
 * ⚠️ **DELIBERATELY NOT EXCLUDED FROM THE METER.** A stamped binding is still
 * billed by Zernio — the account is still connected at their end until they say
 * otherwise — so it must keep costing us on paper exactly as it costs us in
 * fact. Filtering it out of the meter would restore the invisibility 4730 kept
 * the ledger to defeat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gbp_account_bindings', function (Blueprint $table): void {
            $table->timestamp('revocation_owed_at')->nullable();
        });

        // Partial, on the sweep's own predicate. The rows it must never return
        // outnumber the ones it wants by every connected account on the
        // platform, and in the steady state this index is empty — which is the
        // state the whole slice exists to keep it in.
        DB::statement(<<<'SQL'
            CREATE INDEX gbp_account_bindings_revocation_owed_index
                ON gbp_account_bindings (revocation_owed_at)
                WHERE revocation_owed_at IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS gbp_account_bindings_revocation_owed_index');

        Schema::table('gbp_account_bindings', function (Blueprint $table): void {
            $table->dropColumn('revocation_owed_at');
        });
    }
};
