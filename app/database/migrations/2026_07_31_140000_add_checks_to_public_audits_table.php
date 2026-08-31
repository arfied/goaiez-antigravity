<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Row 2 slice E: the roster of which checks ran, beside the findings they made.
 *
 * WHY THIS IS NOT PART OF `findings`. Slice A created that column as a list of
 * findings and DATA-MODEL §5.12 documents it that way. A check that could not
 * run produces no findings — that is the entire point of it — so recording it
 * inside a list of findings would mean inventing a finding-shaped row that is
 * not a finding, which is the exact fabrication `BUILD-PLAN` §2.5.3 tells this
 * slice not to commit.
 *
 * WHY IT IS RECORDED AT ALL, rather than inferred from which checks are missing
 * from `findings`. Absence cannot carry a reason, and the reason is what the
 * public page renders: "we could not read your website" and "your website is
 * fine" are different sentences, and `40` §6.4 requires them to stay different
 * all the way to the screen. It is also the difference between an audit run
 * while the Places budget was exhausted and one run against a business with
 * nothing wrong — which, without this column, are indistinguishable rows with a
 * high score and few findings.
 *
 * Defaulted to `[]` for the same reason `findings` is: a queued audit and an
 * audit that checked nothing are both legitimately empty, and NULL would make
 * every reader handle a third case that means nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_audits', function (Blueprint $table): void {
            $table->jsonb('checks')->default('[]')->after('findings');
        });
    }

    public function down(): void
    {
        Schema::table('public_audits', function (Blueprint $table): void {
            $table->dropColumn('checks');
        });
    }
};
