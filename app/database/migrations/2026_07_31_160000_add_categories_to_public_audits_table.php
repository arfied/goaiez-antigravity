<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The business categories Google already told us, kept instead of discarded.
 *
 * ADDED BY SLICE H, TO A TABLE SLICE A DESIGNED. `BUILD-PLAN` §2.5.2 specifies
 * the wizard pre-fill as copying "name, findings and categories" — and the first
 * two were on the row while the third was not. The audit *reads* categories
 * throughout: GbpCompletenessCheck asks whether the listing has any, and
 * AuditContextBuilder uses `primaryType` to find same-category neighbours. It
 * simply never wrote them down.
 *
 * SO THIS COSTS NOTHING TO POPULATE. The categories arrive inside the Place
 * Details response the audit has already paid 2.50c for (PlacesSku). Persisting
 * them is the difference between the wizard knowing what kind of business this
 * is and asking a question Google answered twenty seconds earlier.
 *
 * `jsonb` rather than a string, because a listing has a primary type and any
 * number of secondary ones, and the wizard wants the primary while the preset
 * matcher wants the lot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_audits', function (Blueprint $table): void {
            // Defaulted to an empty array rather than nullable, for the reason
            // `findings` is: the read paths iterate it, and "no categories" and
            // "not looked yet" are already distinguished by `status`.
            $table->jsonb('categories')->default('[]')->after('name_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('public_audits', function (Blueprint $table): void {
            $table->dropColumn('categories');
        });
    }
};
