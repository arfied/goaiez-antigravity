<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delete — the last of D-205's three contact states (`34` §1.2, `44` §8).
 *
 * A tombstone, not a purge. The row survives forever; what expires is the
 * owner's ability to undo, seven days after the click.
 *
 * ⚠️ **THE ROW IS NEVER DELETED, AND THAT IS THE WHOLE DECISION** (1540).
 * `consent_records.customer_id` is `cascadeOnDelete`, as are `crm_notes`,
 * `crm_tasks`, `crm_timeline` and both of `customer_merges`' keys — so a real
 * DELETE of a contact destroys the tenant's only proof that the person ever
 * agreed to be messaged, for a claim that carries a private right of action.
 * Decision 555 refused to let an attested import be deleted for the same
 * reason: deletion destroys the artefact an investigator would want, and it
 * would do it silently, a week after a click that said nothing about evidence.
 *
 * ⚠️ **THE IDENTIFIERS STAY ON THE ROW**, unlike a merged-away contact, whose
 * are cleared (1523). They cannot be cleared here: `consent_records` carries no
 * identifier column (1522) and reads the address live off this row, so clearing
 * them would leave every consent record pointing at a contact with no address —
 * which destroys the evidence just as surely as the DELETE this design refuses.
 * The consequence is `UNIQUE(business_id, email)` and `UNIQUE(business_id,
 * phone)`: a deleted contact who submits feedback again cannot be given a fresh
 * row, so `FeedbackSubmission` resurrects the tombstone instead (1543).
 *
 * ⚠️ **NOT Laravel's `SoftDeletes` trait, though it is the same column name.**
 * The name matches the owner-facing word, the way `archived_at` does. Adopting
 * the trait would make `$customer->delete()` soft, hand every caller
 * `forceDelete()` — the one operation this decision exists to forbid — and add
 * a second global scope beneath the tenant scope, on a model whose reads
 * already filter `archived_at` and `merged_into_id` by hand. A lint in
 * `tests/Feature/Architecture/CrmTest.php` asserts `Customer` never adopts it.
 *
 * **No index, deliberately.** `archived_at` has none either: the reads that
 * ask about this column all carry `business_id`, which is indexed and is what
 * RLS predicates on anyway, and a partial index would earn its keep only on the
 * Recently deleted view — a screen that renders one tenant's handful of rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->timestamp('deleted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('deleted_at');
        });
    }
};
