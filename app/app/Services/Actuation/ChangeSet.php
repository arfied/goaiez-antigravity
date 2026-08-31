<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Contracts\CmsAdapter;
use App\Enums\ActuationTier;
use App\Enums\SiteSnapshotState;
use App\Exceptions\SiteChangeRefused;

/**
 * One page, one kind of change, and both sides of it.
 *
 * ⛔ **BOTH SIDES, ALWAYS, AND THE `before` IS NOT AN OPTIONAL FIELD.** `29`
 * §2 rule 32: *every site change snapshots its prior state and is reversible.*
 * A change set carrying only what we intend to write describes an edit nobody
 * can undo, and this type is the last place before the database where that can
 * still be refused — {@see SiteChanges::open()} is where it is.
 *
 * ⚠️ **THE `after` IS INTENT, NOT OBSERVATION.** It is what the adapter was
 * asked to write, known before the write, which is what makes a change set
 * reviewable rather than merely auditable afterwards. What the page actually
 * ended up looking like is measurement's question and slice H's.
 *
 * ⚠️ **TYPED FIELDS, NEVER FREE-FORM HTML** — slice I's endpoint rule stated
 * here because it starts here. Whatever travels through a change set is
 * eventually written into a stranger's page, so the shape carried is a map of
 * named fields to values (`meta_description`, a JSON-LD block, an alt text) and
 * a body of markup would make every adapter an injection surface at once.
 *
 * ## A creation is a change set whose `before` is a recorded absence
 *
 * ⛔ **A PAGE THAT DOES NOT EXIST YET HAS A PRIOR STATE, AND IT IS *"NO PAGE AT
 * THIS URL"*** (5753, ruled at 5770). {@see self::creating()} is the only way to
 * build one, and the `before` it writes is {@see self::ABSENT_PAGE} — a real
 * value, recorded in `site_changes.before_snapshot`, which satisfies
 * `open()`'s rule-32 guard by *saying something true* rather than by being
 * waved past it. **`open()` still refuses an empty `before` and has no creation
 * arm at all.**
 *
 * ⛔ **AND ITS REVERT IS AN UNPUBLISH, NEVER A DELETE** — see
 * {@see CmsAdapter::unpublishPage()}, where the promise is written out. That is
 * why {@see self::inverted()} refuses a creation instead of swapping the sides:
 * the inverse of *"this page did not exist"* is not a field map anything can
 * write.
 *
 * @phpstan-type SnapshotFields array<string, mixed>
 */
final readonly class ChangeSet
{
    /**
     * The `before` of a page that was not there when we looked.
     *
     * ⚠️ **THE KEY CANNOT COLLIDE WITH A FIELD NAME**, which is the whole reason
     * for the leading underscore: every field a change set may carry is a name
     * an adapter maps to a CMS argument (`title`, `content`, `excerpt`,
     * `meta_description`), and a snapshot key that could be mistaken for one of
     * those would be handed to a site as a value to write.
     */
    public const array ABSENT_PAGE = ['_page_state' => 'absent'];

    /**
     * @param  array<string, mixed>  $before  The page's prior state, as read —
     *                                        or {@see self::ABSENT_PAGE}.
     * @param  array<string, mixed>  $after  What the adapter is asked to write.
     * @param  array<string, string>  $withheld  Field name to the reason it is
     *                                           **not** in `$after`. ⛔ **A
     *                                           REFUSAL NOBODY RECORDS IS A
     *                                           SILENT OMISSION** (5772): when a
     *                                           page's meta description cannot
     *                                           be written by the bound adapter,
     *                                           the change set carries the fact
     *                                           and the reason rather than
     *                                           quietly shipping two fields
     *                                           where three were intended.
     *                                           Persisted on
     *                                           `site_changes.withheld_fields`
     *                                           and read back into the audit
     *                                           entry.
     * @param  ?string  $pageRef  The adapter's own name for the page it wrote —
     *                            `pages/51` on WordPress. ⛔ **NULL ON EVERY
     *                            CHANGE SET THAT HAS NOT BEEN APPLIED YET**, and
     *                            on every row written before 6141's column: it
     *                            is not knowable until an adapter has written,
     *                            so it travels **into** the adapter only on the
     *                            revert path, out of
     *                            `site_changes.written_page_ref`. ⚠️ **Nothing
     *                            outside the adapter that produced it may
     *                            interpret it** (5966).
     */
    public function __construct(
        public string $url,
        public string $changeType,
        public ActuationTier $tier,
        public array $before,
        public array $after,
        public array $withheld = [],
        public ?string $pageRef = null,
    ) {}

    /**
     * A page this platform is about to create, at an address nothing answers on.
     *
     * ⛔ **THE CALLER MUST HAVE LOOKED.** The only honest source of this is an
     * adapter answering {@see SiteSnapshotState::Absent} — *we asked
     * the site and no published page claims this URL*. Building one from
     * {@see SiteSnapshotState::Unreadable} would create a page on top
     * of a site we could not see, which is the one direction decision 5770 says
     * must never collapse.
     *
     * @param  array<string, mixed>  $after
     * @param  array<string, string>  $withheld
     */
    public static function creating(
        string $url,
        string $changeType,
        ActuationTier $tier,
        array $after,
        array $withheld = [],
    ): self {
        return new self($url, $changeType, $tier, self::ABSENT_PAGE, $after, $withheld);
    }

    /**
     * Whether this change set brings a page into existence.
     */
    public function isCreation(): bool
    {
        return $this->before === self::ABSENT_PAGE;
    }

    /**
     * The change set that puts this page back.
     *
     * ⚠️ **A REVERT IS A CHANGE SET LIKE ANY OTHER**, with the two sides
     * swapped — which is why the adapter's `rollback()` takes one of these
     * rather than a model, and why nothing on the revert path needs a second
     * kind of write.
     *
     * ⛔ **EXCEPT A CREATION, WHICH HAS NOTHING TO SWAP.** Inverting one would
     * ask an adapter to write `_page_state: absent` onto a stranger's page as
     * though it were content. The revert of a creation is
     * {@see CmsAdapter::unpublishPage()} and {@see SiteChanges::revert()} routes
     * it there; this refusal is what stops a second caller taking the ordinary
     * path with a value that looks close enough.
     *
     * @throws SiteChangeRefused
     */
    public function inverted(): self
    {
        if ($this->isCreation()) {
            throw SiteChangeRefused::creationCannotBeInverted($this->url);
        }

        // ⚠️ **THE PAGE REFERENCE SURVIVES THE INVERSION** (6141). The sides
        // swap; *which page* does not, and it is the whole reason the adapter
        // can still find a page whose slug the owner has since changed.
        return new self($this->url, $this->changeType, $this->tier, $this->after, $this->before, $this->withheld, $this->pageRef);
    }
}
