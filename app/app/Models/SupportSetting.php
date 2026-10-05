<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\CallRoutingMode;
use App\Enums\LiveAnswerMode;
use Database\Factories\SupportSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Per-business support desk and call-routing configuration (DATA-MODEL §5.9).
 *
 * ⛔ **THIS MODEL HAD NO WRITER ANYWHERE IN `app/` UNTIL 2026-08-12** — decision
 * 272's shape, the sixteen-instance list in `CLAUDE.md`, and **no tenant had a
 * row at all**. `App\Services\Voice\CallForwarding` is the first, and it writes
 * exactly one of these columns (`call_routing_mode`). Everything else here is
 * still writerless: `business_hours`, `escalation_contacts`, `blocked_topics`,
 * `agent_schedule`, `on_call_numbers`, `sla_minutes`, `retention_days`,
 * `voicemail_greeting_type` and both greeting media ids.
 * **Check for a writer before depending on any of them** (1222) — reading one
 * today renders a database default, not a decision anybody made.
 *
 * ⛔ **THAT LIST NAMES TEN AND THE ANSWER IS FIFTEEN OF SEVENTEEN, AND IT IS
 * WRONG IN BOTH DIRECTIONS AT ONCE — MEASURED 2026-08-23 (8552).** The list is
 * kept above and dated here rather than rewritten (4368's rule), because **it is
 * this slice's own best evidence**: a hand-written census, in the file every
 * reader of this model opens, corrected twice already, that drifted anyway.
 *
 *  - **It omits three.** `after_hours_message`, `live_agent_enabled` and
 *    `booking_enabled` are named by nothing outside this file either, and were
 *    never on it.
 *  - **Three of the ten it names now read as *alive* to any grep**, because
 *    their only occurrence outside `app/Models` is a paragraph — this one, and
 *    `CallForwarding`'s — recording that they are dead: `on_call_numbers`,
 *    `voicemail_greeting_type` and `forwarding_broken_at`. **Writing the epitaph
 *    is what hid the corpse**, and it is why `CLAUDE.md`'s writerless count
 *    *"has never been the population"* and structurally could not be.
 *  - **And `retention_days` reads alive from a bare-token collision**:
 *    `ExportBuilder` writes an unrelated `'retention_days'` key and
 *    `StorageRetention` owns the prefix `storage.retention_days.`. Neither is
 *    this column.
 *
 * ⚠️ **SO ONLY TWO OF THE SEVENTEEN NON-KEY COLUMNS HERE ARE NAMED BY ANY CODE
 * AT ALL** — `call_routing_mode` and `ring_timeout_seconds`, both reached by
 * `CallForwarding` and nothing else. Every other value on this table is a
 * database default no code path has ever consulted.
 *
 * ⛔ **DO NOT UPDATE THE LIST ABOVE WHEN THIS CHANGES — RE-DERIVE IT.**
 * `php artisan db:column-readers --table=support_settings` answers the question
 * from `pg_attribute` and a comment-stripped scan of the tree, which is the one
 * form of this census that cannot be out of date. A prose list is what produced
 * every error above.
 *
 * ⛔ **`emergency_keywords` WAS ON THAT LIST AND IS NOW GONE FROM THE TABLE**
 * (6880–6890). It carried `29` §19.6's *"escalate immediately, including
 * overnight"* and a seeded list of six words on every row, and it had no reader
 * and no writer — 272's shape, with the spec citation that makes a dead column
 * read as the answer. **§19.6's escalation vocabulary is `urgent_terms`**, which
 * has the matching rule, the owner's screen and the escalation; a business gets
 * one list of words that mean drop everything, not one per channel. Do not
 * reintroduce a second store here — `tests/Feature/Architecture/PricesTest.php`
 * fails the build on one.
 *
 * ⚠️ **`voicemail_greeting_type` KEEPS ITS PLACE ON THE LIST BUT NO LONGER
 * DEFAULTS TO `'tts'`** (6892–6895). It is `null` — *nobody has chosen* — because
 * `'tts'` reads as *synthesise during the call* under one of the two readings
 * `CLAUDE.md` supports, and that one is forbidden outright. Whoever writes the
 * first reader owes the ruling on which reading was meant, and a backed enum in
 * `app/Enums` for the values.
 *
 * ⛔ **`forwarding_verified_at` AND `forwarding_broken_at` ARE DELIBERATELY
 * UNWRITTEN** (2913). Verification means a real test call through the Infobip
 * Voice API and that activation is 2109's outstanding external ask. A timestamp
 * stamped by a save button would say a forward was confirmed by something that
 * has never dialled a number. Whoever builds the voice path writes both.
 *
 * ⭐ **2026-10-05 — `live_agent_enabled` IS GONE AND `live_answer_mode` REPLACES IT** (owner ruling D-6, AI receptionist
 * plan). The boolean had no reader and no writer; the receptionist needed one per-business answer instead — the AI picks up
 * straight away (`ai_first`, the default) or the owner is rung first (`owner_first`). `CallForwarding` writes and reads it.
 * The census above is kept as it was written, for the reason it gives.
 *
 * @property int $business_id
 * @property CallRoutingMode $call_routing_mode
 * @property int $ring_timeout_seconds
 * @property LiveAnswerMode $live_answer_mode
 * @property ?Carbon $forwarding_verified_at
 * @property ?Carbon $forwarding_broken_at
 */
final class SupportSetting extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<SupportSettingFactory> */
    use HasFactory;

    /**
     * What the carrier's no-reply timer is set to when nobody has chosen.
     *
     * ⚠️ **A COPY OF `2026_07_30_111421_create_support_settings_table`'s COLUMN
     * DEFAULT, AND THE ONLY REASON IT MAY EXIST IS THAT A TEST COMPARES THEM.**
     * `CallForwarding` builds a dial string for a tenant with no row at all —
     * lazily created is the design, see that class — so it needs the figure the
     * database *would* apply, before there is a row to read it from. A second
     * copy of a schema default is exactly the drift `CLAUDE.md` warns about, so
     * `CallForwardingTest` creates a row without touching the column and asserts
     * the two agree. `29` §19.6 is where 18 comes from: the forward must fire
     * before the carrier's own voicemail could answer.
     */
    public const int DEFAULT_RING_TIMEOUT_SECONDS = 18;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_hours' => 'array',
            'escalation_contacts' => 'array',
            'blocked_topics' => 'array',
            'agent_schedule' => 'array',
            'on_call_numbers' => 'array',
            'live_answer_mode' => LiveAnswerMode::class,
            'booking_enabled' => 'boolean',
            'call_routing_mode' => CallRoutingMode::class,
            'forwarding_verified_at' => 'datetime',
            'forwarding_broken_at' => 'datetime',
        ];
    }
}
