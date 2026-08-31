<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The three switches §2.4 gives a business over its assistant — T176 P4.
 *
 * *"toggles: quotes on/off · review-ask on/off · nudge on/off."* They land on
 * `assistant_briefs` rather than on a table of their own for that table's own
 * reason: this is the row that holds the answers which are one value each, and
 * three booleans are three more of those. The row already carries RLS, its
 * policy and its unique `business_id`, so nothing about isolation changes here.
 *
 * ## ⚠️ NULLABLE, AND THE NULL IS LOAD-BEARING
 *
 * A `boolean` defaulting to `true` would have been shorter and is wrong. Most
 * businesses will never have an `assistant_briefs` row at all — it appears the
 * first time somebody answers one of the wizard's questions — so the reader has
 * to resolve *"no row"* to the default anyway. Giving the column a default as
 * well produces **two** places the default lives, which drift the first time
 * somebody changes one: a tenant with a row reads the column's default and a
 * tenant without one reads the enum's, and nothing says which is in force.
 *
 * `NULL` therefore means *"nobody has touched this switch"* and is answered by
 * `AssistantToggle::defaultsOn()` — one default, in code, for both populations.
 * It is `reviews.default_invite_threshold`'s shape (1420) spelled as a boolean:
 * a tenant who has chosen has a stored answer, and a tenant who has not follows
 * the platform.
 *
 * ⛔ **AND ALL THREE DEFAULT *ON*, WHICH IS NOT THE CONSERVATIVE-LOOKING
 * CHOICE.** §2.2 says *"toggle, default ON"* for the review ask and the nudge
 * outright. A switch that ships off is a feature nobody finds, and this
 * platform's standing answer to that is `automation_mode = 'auto'`. The switch
 * exists so a business can **stop** something.
 *
 * ⛔ **NO CHECK CONSTRAINT AND NOTHING TO CONSTRAIN.** All eight combinations of
 * three booleans are legal states a business may want, including all three off —
 * which is a business that wants capture-and-handoff and nothing else, and is
 * exactly what §2.4's skipped-wizard posture already is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_briefs', function (Blueprint $table): void {
            // Skill 4. ⚠️ AND-ED WITH THE PRICE LIST, NEVER INSTEAD OF IT — a
            // business may have priced twelve jobs and still not want figures
            // said over text, and "I turned this off" and "I priced nothing" are
            // two states with two different remedies on the owner's screen.
            $table->boolean('quotes_enabled')->nullable();

            // Skill 13, the bridge to SL-1. The permit, the invite ledger and
            // the per-destination threshold are all still upstream of this: it
            // is the business's own "do not ask on my behalf", not a way past
            // any of them.
            $table->boolean('review_ask_enabled')->nullable();

            // Skill 14. One nudge, inside 24h, quiet-hours-respecting — this
            // switch is whether that one happens at all.
            $table->boolean('nudge_enabled')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assistant_briefs', function (Blueprint $table): void {
            $table->dropColumn(['quotes_enabled', 'review_ask_enabled', 'nudge_enabled']);
        });
    }
};
