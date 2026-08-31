<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Enums\ActuationTier;
use App\Enums\SiteChangeUndoState;
use App\Enums\SiteChangeVerdict;
use App\Livewire\Account\SiteChanges as SiteChangesScreen;
use App\Services\Content\Publishing;
use Carbon\CarbonImmutable;

/**
 * One card on the owner's undo screen — `BUILD-PLAN` §2.11.3 slice J.
 *
 * ⛔ **IT CARRIES NO PAGE CONTENT AND THAT IS NOT AN OVERSIGHT.** `SiteChange`
 * holds both snapshots in full, and a snapshot read off a live site can contain
 * anything that was on that page — a phone number, a booking reference, on a
 * healthcare tenant text rule 24 fences. {@see SiteChanges::live()}'s docblock
 * already says a whole `ChangeSet` must not leave the server; this is the shape
 * that may, and it is why the screen is handed these rather than models.
 *
 * ⚠️ **IT ALSO KEEPS THE MODEL OUT OF THE SCREEN, WHICH THE CHOKEPOINT LINT
 * REQUIRES** (5521). Only `SiteChanges` and `SiteMeasurements` may name that
 * model at all — **including this file, which is why nothing here builds one of
 * these from a row**; {@see SiteChanges::history()} is where the mapping lives.
 * The accident of the lint is the right architecture anyway: the screen renders
 * facts, and every question about what may still be done is answered before it
 * gets there.
 *
 * @see SiteChangesScreen
 */
final readonly class OwnerSiteChange
{
    /**
     * @param  list<string>  $withheld  Owner-facing sentences, never field names.
     */
    public function __construct(
        public int $id,
        public int $locationId,
        public string $locationName,
        public string $url,
        public ActuationTier $tier,
        public bool $createdThePage,
        public CarbonImmutable $appliedAt,
        public SiteChangeUndoState $state,
        public ?CarbonImmutable $undoneAt,
        public ?string $undoneReason,
        public array $withheld,
        /**
         * What measuring this change found, or null when nothing has.
         *
         * ⛔ **THE READER 5819 AND 5849 BOTH RECORDED AS OWED**, and it arrives
         * here rather than on a screen of its own: 5849's objection was that
         * *"a metrics panel is a report and this screen is a control"*, which is
         * an argument against a **panel of numbers** and not against telling
         * somebody what happened to their page. What this carries into the view
         * is one sentence.
         */
        public ?ChangeEvidence $evidence = null,
    ) {}

    /**
     * What we found out after we changed this page — one sentence, or nothing.
     *
     * ⛔ **"WE HAVE NOT LOOKED YET" AND "WE LOOKED AND COULD NOT TELL" ARE TWO
     * ANSWERS AND BOTH ARE PRINTED** (229, and {@see SiteChangeVerdict}'s own
     * rule that `InsufficientData` never collapses into `Neutral`). The measured
     * window does not open for a fortnight and an unsettled Search Console
     * window defers the whole measurement (5802), so **most cards on most days
     * have no evidence** — and a card that said nothing at all about it would
     * leave an owner assuming we never check.
     *
     * ⛔ **AND THE WAITING SENTENCE IS ONLY TRUE WHILE THE CHANGE IS STILL
     * THERE.** A change that came off the site before its window opened is not
     * being watched, and telling somebody we are watching a page we already put
     * back would be a false statement about their own website.
     */
    public function resultSentence(): ?string
    {
        if ($this->evidence !== null) {
            return $this->evidence->sentence();
        }

        return $this->state->stillOnTheSite()
            ? 'We are still watching this page. We look at how it is doing between two weeks and a '
                .'month after a change, and whatever we find turns up here.'
            : null;
    }

    /**
     * The heading — what we did, in one sentence.
     */
    public function heading(): string
    {
        return $this->createdThePage
            ? 'We added a page to your website'
            : 'We changed a page on your website';
    }

    /**
     * The address, without the scheme, because the owner knows their own site.
     *
     * ⚠️ **THE QUERY STRING IS DROPPED RATHER THAN TRIMMED FOR WIDTH.** A full
     * URL with a query string is where a booking reference or an email address
     * ends up, which is the same rule `ChangeMeasurer`'s log line follows and
     * `LogCmsAdapter`'s path-only logging established (5529).
     */
    public function displayUrl(): string
    {
        $withoutQuery = (string) strtok($this->url, '?');

        return (string) preg_replace('#^https?://#', '', $withoutQuery === '' ? $this->url : $withoutQuery);
    }

    /**
     * Turn the fields a change set could not carry into sentences an owner can
     * act on — decision 5836.
     *
     * ⛔ **NEVER THE FIELD NAME, AND NEVER THE ADAPTER'S REASON.**
     * `site_changes.withheld_fields` holds `meta_description` mapped to *"not
     * writable over core REST"* — both correct, both about our machinery, and
     * `29` §2 rule 47 permits neither on a screen. What the owner gets is the
     * thing they lost and where they can set it themselves.
     *
     * ⛔ **A FIELD WITH NO SENTENCE FALLS BACK RATHER THAN THROWING, AND A LINT
     * IS WHAT STOPS THE FALLBACK BEING THE ANSWER.** Raising inside a render
     * would take down the screen an owner opened to be reassured. So the
     * fallback is true for any field there could ever be — *we could not set
     * everything we wanted to* — and `Architecture\ActuationTest` fails the
     * build if any field in {@see Publishing}'s own list arrives here without a
     * sentence of its own. 5748's lesson stated the other way round: a `default`
     * arm is only safe when something else notices the set widening.
     *
     * @param  array<string, string>  $withheld  field => the adapter's reason
     * @return list<string>
     */
    public static function withheldSentences(array $withheld): array
    {
        $sentences = [];

        foreach (array_keys($withheld) as $field) {
            $sentences[] = match ($field) {
                'meta_description' => 'We could not set the short summary that shows under this page in Google. '
                    .'Your website does not give us a way to change it — you can set it yourself where you edit the page.',
                'title' => 'We could not set this page\'s title. You can set it yourself where you edit the page.',
                'content' => 'We could not set the words on this page. You can write them yourself where you edit the page.',
                default => 'We could not set everything we wanted to on this page. '
                    .'Ask us and we will tell you exactly what is missing.',
            };
        }

        return $sentences;
    }
}
