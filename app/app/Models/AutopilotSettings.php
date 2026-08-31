<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\AutomationMode;
use App\Enums\BrandVoice;
use Database\Factories\AutopilotSettingsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-location autopilot configuration (DATA-MODEL §5.4), one row per
 * location. Defaults are ungated (`29` §2 rule 37); the CONFIRM list is not
 * the owner's to shrink.
 *
 * ⛔ **NINE OF THIS TABLE'S SEVENTEEN NON-KEY COLUMNS HAVE NO READER, AND
 * `require_confirm_for` IS ONE OF THEM — MEASURED 2026-08-23 (8551).** Eight
 * are named by nothing outside this file at all — `hold_window_overrides`,
 * `require_confirm_for`, `post_to_google_profile`, `post_to_facebook`,
 * `recycle_reviews_to_social`, `support_bot_enabled`, `outreach_channels`,
 * `brand_voice_examples` — and the ninth, `automation_mode`, was **write-only**:
 * its one non-comment occurrence was a form field on `Admin\LocationSettings`,
 * so staff could set a value nothing consults. Re-derive with
 * `php artisan db:column-readers --table=autopilot_settings`; do not trust this
 * paragraph, which is prose and will go stale exactly as `SupportSetting`'s did.
 *
 * ⛔ **AND IT WENT STALE INSIDE THE WAVE THAT WROTE IT, WHICH IS THE DEMONSTRATION
 * RATHER THAN AN EMBARRASSMENT — 8568.** Lane D reached `automation_mode` from
 * the opposite direction in the same wave, found it *"the only one of that Ops
 * form's seven fields without a reader"*, and **removed the field** — arguing
 * that a control which visibly does nothing moves the ticket from *"I set
 * Confirm and it still posted"* to *"when does Confirm start working?"*. So on
 * the merged tree the column is not write-only; it is **silent, and nine of the
 * nine are**. ⚠️ **Both readings are kept and dated** (4368): the write-only one
 * was true at `1aef27c6` and is what a reader briefed from this file believed
 * for a day. **4700's lesson, arriving inside the slice that cites it** —
 * neither lane was wrong on its own branch, and nothing either could run would
 * have said so. **The derived census is the only form of this paragraph that
 * survives a merge.**
 *
 * ⛔ **SO *"the CONFIRM list is not the owner's to shrink"* IS A CLAIM ABOUT A
 * COLUMN NOTHING READS.** Shrinking it changes nothing, growing it changes
 * nothing, and `29` §2 rule 38 — the rule that sentence is quoting — has no
 * implementation anywhere. ⚠️ **The citation is also one rule out**: rule 37 is
 * the three *patterns*, rule 38 is *reserved for exactly three things*. The
 * create-migration makes the same slip.
 *
 * ⚠️ **`TenantProvisioningTest` PINS THE THREE VALUES AND IS RIGHT TO** (5322).
 * It is correct about the value and wrong about the consequence: its comment
 * says a fourth entry *"would put an unannounced approval step in front of an
 * automation"*. It would not — nothing reads the column. **Do not weaken that
 * test**; the missing half is asserted in
 * `tests/Feature/ConfirmIsUnbuiltTest.php`.
 *
 * ⛔ **`support_bot_enabled` IS THE SHARPEST OF THE EIGHT AND IS NOT A CONFIRM
 * PROBLEM** (8553). It defaults `true`, it is cast here, and it names the
 * assistant that texts members of the public — while the switches that are
 * actually honoured are `assistant_briefs.{quotes,review_ask,nudge}_enabled`,
 * read only through `AssistantToggles` and held there by a chokepoint lint.
 * ⛔ **THAT NAME IS BARE ON PURPOSE AND MUST NOT BE TIDIED INTO A `{@see}` OR A
 * FULLY-QUALIFIED REFERENCE** (8573). `composer lint`'s
 * `fully_qualified_strict_types` hoists a namespaced name **out of a docblock
 * and into a real `use` statement**, backticks and all — so a paragraph
 * *describing* a service becomes a Model importing one, and the chokepoint
 * lints that scan through `codeWithoutComments()` exist precisely so prose
 * cannot satisfy a rule about making a call. **The formatter runs between the
 * author and the lint.** This happened here once already and was caught. **Two switches for one capability and one of them
 * is wired.** ⚠️ **And the three that are wired are per *skill*: there is no
 * master switch for the assistant at all**, so a business cannot stop it
 * replying. Reading this column as that switch is the mistake to avoid.
 *
 * ⚠️ `triage_threshold` IS WRITTEN BY EXACTLY ONE CLASS — `ReviewGating`,
 * COMP-02's service — and a lint holds it there. It is read by `ReviewRouter`,
 * which compares it in the opposite direction to
 * `review_destinations.invite_threshold`, so the two only mean anything as a
 * pair (1186).
 *
 * ⚠️ **`gating_ack_at` WAS HERE AND IS GONE** (decisions 2074, 2660). It was
 * read by `ReviewRouter` as permission to apply any invite threshold at all
 * (114, 290, 374), and because nothing provisioning created ever set it, the
 * effect was that no tenant had any threshold applied. The owner removed the
 * acknowledgement on 2026-08-11; the column was dropped on 2026-08-12 and
 * thresholds now apply from the resolved value with nothing acknowledged.
 *
 * ⚠️ **`update_review_hub` IS AN OFF SWITCH FOR A PUBLIC PAGE, AND IT SPENT ITS
 * WHOLE LIFE WITHOUT A WRITER** (6623, closed at 6860). It defaults `true`;
 * `ReviewHubPages::publishedFor()` has read it on every request to `/r/{slug}`
 * since the hub shipped; and nothing in `app/` could set it, so a location's
 * approved reviews could be published at a public address and never taken down
 * again. `Admin\LocationSettings` is now the one writer — **platform staff
 * only**, because a tenant-facing control here would be `CLAUDE.md`'s forbidden
 * toggle and 1143 shows what overriding that rule takes (6862).
 *
 * ⚠️ **OFF MEANS TAKE THE PAGE DOWN, NOT STOP REFRESHING IT** (6547), and a
 * location with no row at all is treated as off (6548) even though this column
 * defaults on — the safe reading of *"I cannot tell"* on a publication surface
 * is not to publish. **Both readings live in `ReviewHubPages`, not here**: this
 * column carries no meaning of its own and changing what it means is a change
 * to the reader.
 *
 * @property int $triage_threshold
 * @property bool $send_review_requests
 * @property bool $update_review_hub
 * @property BrandVoice $brand_voice
 * @property int $reply_auto_post_min
 * @property bool $auto_reply
 * @property bool $full_auto_post_replies
 */
final class AutopilotSettings extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<AutopilotSettingsFactory> */
    use HasFactory;

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
            'automation_mode' => AutomationMode::class,
            'brand_voice' => BrandVoice::class,
            'hold_window_overrides' => 'array',
            'require_confirm_for' => 'array',
            'outreach_channels' => 'array',
            'triage_threshold' => 'integer',
            'reply_auto_post_min' => 'integer',
            'auto_approve_5_star' => 'boolean',
            'auto_reply' => 'boolean',
            'full_auto_post_replies' => 'boolean',
            'send_review_requests' => 'boolean',
            'post_to_google_profile' => 'boolean',
            'update_review_hub' => 'boolean',
            'post_to_facebook' => 'boolean',
            'recycle_reviews_to_social' => 'boolean',
            'support_bot_enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
