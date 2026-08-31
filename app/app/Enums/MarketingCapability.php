<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What the signed-out site is allowed to say we do — CC-2 §2.3 and §2.4.
 *
 * ⛔ **ONE SOURCE, TWO RENDERS**, which is the pattern the patch bridge asks for
 * by name (`00-CLAUDE-CODE-START-HERE.md` §4, and `index_mode`'s shape one slice
 * over). `/features` renders a section per capability and `/compare` renders a
 * row per capability, and **both ask this enum whether the capability is live**.
 * Two lists would drift, and they would drift in the direction that matters: a
 * comparison table claiming a store we have not shipped, on the page whose entire
 * subject is honesty about what the alternatives do.
 *
 * ⚠️ **A `null` FLAG MEANS LIVE, NOT "UNGATED BY OVERSIGHT".** The three live
 * capabilities are the three the product does today; everything else ships dark
 * behind a seeded-`false` registry row and turns on without a deploy. There is
 * deliberately no flag for the live three — a flag that has never been off is a
 * switch nobody has tested, and turning the missed-call text-back off is not a
 * marketing decision.
 *
 * ⚠️ **THE COPY IS NOT HERE.** `CLAUDE.md`'s binding rule for this surface is that
 * prose may live in a blade and numbers may not; this enum is the structure, and
 * `resources/views/marketing/features.blade.php` carries the sentences.
 */
enum MarketingCapability: string
{
    case TextBack = 'text_back';
    case Reviews = 'reviews';
    case FrontDesk = 'front_desk';
    case Commerce = 'commerce';
    case Campaigns = 'campaigns';
    case Inbox = 'inbox';
    case Websites = 'websites';
    case BoostScore = 'boost_score';

    /**
     * Every capability whose flag is a **claim switch**, keyed by its registry row.
     *
     * ⛔ **THE DISTINGUISHING PROPERTY IS NOT "SEEDED FALSE"** (11702). Plenty of
     * live flags in `DefaultsManifest` seed false and mean *"not turned on here
     * yet"*. These five mean *"we do not do this"*, and their only reader is a
     * marketing render rather than the code path they name — {@see self::flagKey()}
     * has exactly one consumer in `app/`, `MarketingController`, feeding
     * `features()`, `compare()` and `faq()`. **No tenant-facing screen, job,
     * service or route reads any of the five.**
     *
     * ⚠️ **DERIVED FROM {@see self::flagKey()} RATHER THAN LISTED**, so a
     * capability that gains a flag joins this set in the same edit that gives it
     * one. A second list would be the drift this enum exists to prevent, one level
     * up from the two templates it was written for.
     *
     * ⛔ **`features.industry_pages` IS DELIBERATELY NOT IN HERE AND IS NOT AN
     * OMISSION.** It is the sixth `features.*` key and the only one that is a real
     * functional gate: `App\Services\Industries\IndustryPages::enabled()` reads the row and
     * `RequireIndustryPages` aborts 404 on it, so **a whole route block answers or
     * does not answer** — flipping it publishes a hundred URLs rather than a
     * sentence, and it has a seeder behind it. It carries no case in this enum,
     * which is what keeps it out.
     *
     * ⚠️ **NO COUNT OF ITS READERS IS WRITTEN HERE AND ONE WAS — 11709.** The
     * figure this paragraph carried was *"seven readers across two controllers, a
     * middleware, a service and `routes/web.php`"*, taken from a brief. Re-derived
     * with comments stripped, the key is spelled in code in **`MarketingController`,
     * `IndustryPages` and the manifest**; `RequireIndustryPages`, `RobotsController`,
     * `SitemapController`, `DefaultsRegistry`, `routes/web.php` and this file
     * mention it **only in prose**. The middleware is still a real reader — it goes
     * through the service — which is exactly why a count of spellings answers a
     * different question than it looks like it answers (11271, 11422). **The
     * property is that it gates a route block; the census is `grep`'s to run.** `MarketingTest`'s *"every features.* key is a capability claim
     * switch or a named exception"* asserts both directions of that.
     *
     * @return array<string, self>
     */
    public static function claimSwitches(): array
    {
        $switches = [];

        foreach (self::cases() as $capability) {
            $key = $capability->flagKey();

            if ($key !== null) {
                $switches[$key] = $capability;
            }
        }

        return $switches;
    }

    /**
     * The capability a registry row publishes, or null when the row is not one.
     */
    public static function claimSwitchFor(string $key): ?self
    {
        return self::claimSwitches()[$key] ?? null;
    }

    /**
     * The registry row that flips this capability on, or null when it is live.
     */
    public function flagKey(): ?string
    {
        return match ($this) {
            self::TextBack, self::Reviews, self::FrontDesk => null,
            self::Commerce => 'features.commerce',
            self::Campaigns => 'features.campaigns',
            self::Inbox => 'features.inbox',
            self::Websites => 'features.websites',
            self::BoostScore => 'features.boost_score',
        };
    }

    /**
     * The signed-out pages that start saying this, by the label the site's own
     * navigation gives them.
     *
     * ⛔ **THE CONFIRMATION PANEL PROMISED ALL THREE FOR ALL FIVE AND THAT IS
     * TRUE OF `features.commerce` ALONE** (12090, 12244). Rendered off Ops,
     * Platform, Settings and read as text, an operator turning on
     * `features.inbox` — which reaches `/features` and nothing else — was told
     * their press would add *"a row on the comparison, and an answer in the
     * questions."* ⚠️ **The over-statement is in the cautious direction and is
     * still wrong**: the panel exists so that a person is told what becomes
     * public before they make it public, and **a blast radius stated wider than
     * it is teaches the reader that the sentence is decoration.** ⚠️ **It also
     * contradicted the line directly above it** — the manifest description for
     * `features.inbox` names no comparison row, because there is not one.
     *
     * ⛔ **SO THE PANEL RENDERS THIS RATHER THAN A PARAGRAPH**, and this is the
     * only copy: `MarketingTest`'s *"a claim switch reaches the signed-out views
     * the confirmation panel says it does"* derives the same map **from every
     * blade in the tree** and asserts it against this method in both directions,
     * so the edit that keeps the build green is the edit that keeps the screen
     * honest. The literal it used to hold was a second copy of the tree with no
     * consumer; this one has a reader.
     *
     * ⚠️ **THE LABELS ARE THE NAV'S, DELIBERATELY** — `components/marketing/layout`
     * spells them, an operator reads them there, and a page named any other way
     * is a page they have to go looking for. The same lint asserts every label
     * returned here still appears in that nav.
     *
     * @return list<string>
     */
    public function publishedSurfaces(): array
    {
        return match ($this) {
            self::Commerce => ['What it does', 'Compare', 'Questions'],
            self::TextBack, self::Reviews, self::FrontDesk, self::Campaigns => ['What it does', 'Compare'],
            self::BoostScore => ['What it does', 'Questions'],
            self::Inbox, self::Websites => ['What it does'],
        };
    }

    /**
     * The heading the section carries on `/features`.
     *
     * A label rather than a sentence, which is why it is here and the paragraph
     * beneath it is not: the same word has to appear on `/compare`'s row for the
     * two pages to be describing one thing, and a heading duplicated across two
     * templates is the smallest possible version of the drift this enum exists to
     * prevent.
     */
    public function heading(): string
    {
        return match ($this) {
            self::TextBack => 'Missed calls, texted back',
            self::Reviews => 'Reviews, asked for at the right moment',
            self::FrontDesk => 'A front desk that never sleeps',
            self::Commerce => 'Sell it, book it, gift it',
            self::Campaigns => 'Campaigns already written',
            self::Inbox => 'One thread per human',
            self::Websites => 'A site you talk into being',
            self::BoostScore => 'One honest number',
        };
    }
}
