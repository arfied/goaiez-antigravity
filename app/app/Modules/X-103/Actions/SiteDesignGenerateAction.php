<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Enums\AiTask;
use App\Models\Business;
use App\Models\Review;
use App\Modules\X103\Domain\BlockPatchSchema;
use App\Modules\X103\Domain\CompetitorDigest;
use App\Modules\X103\Domain\SiteBlockRenderer;
use App\Modules\X103\Domain\SiteDesignEngines;
use App\Modules\X103\Domain\SiteFonts;
use App\Modules\X103\Domain\SiteTemplates;
use App\Modules\X103\Domain\SiteThemes;
use App\Modules\X103\Domain\StatedFacts;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\SiteInventoryImage;
use App\Modules\X103\Models\SiteInventoryPage;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AiRouter;
use App\Services\Assistant\PriceBook;
use App\Services\Config\DefaultsRegistry;
use App\Services\Industry\IndustryStartingPoints;
use App\Services\Industry\SiteStyle;
use App\Support\PlanPricing;
use Throwable;

/**
 * The AI designer, JSON in and JSON out (the boss, 2026-10-02, adopting Gemini's advice and the OpenPage architecture).
 *
 * The AI never writes HTML. It returns one JSON object: a theme id from SiteThemes, optional brand colours and fonts,
 * and the page as an ordered list of our typed sections (hero, services, reviews, FAQ, stats, call to action, contact…)
 * with their fields and layout variants. Every section goes through BlockPatchSchema::modelBlock — the same field and
 * link rules as every AI edit — and the renderer's own validity check, so the page is drawn by our components and every
 * module that reads sections (contact form, search data, reviews, booking, tracking) keeps working. Each AI's design is
 * kept separately on the page (draft_meta.designs.<engine>) so the four can be compared; nothing is published here.
 */
final class SiteDesignGenerateAction
{
    /** The section types the designer may use, with the fields each takes — what the AI is told, in its own words. */
    private const CATALOGUE = 'hero (headline, subline, cta_label, cta_url; variant "split" | "centered" | "cover") · '
        .'stats (heading, items: value, label; variant "row" | "cards" | "bar") · services (heading, items: name, description, price_text; variant "cards" | "list" | "columns") · '
        .'about (heading, text; variant "plain" | "centered" | "split") · reviews_strip (heading, items: author, rating, text, source; variant "cards" | "quote" | "row") · '
        .'team (heading, items: name, role) · faq (heading, items: question, answer; variant "list" | "cards" | "columns") · cta_band (heading, text, label, url) · '
        .'booking_button (label, url; variant "inline" | "banner" | "card") · contact (address, phone, email; variant "stack" | "columns" | "card")';

    /** What each page of a whole site is for (SiteBuildWholeAction), told to the AI so the four pages do not repeat each other. */
    /**
     * On a site template, the sections the AI never writes. A team section names real people who work here (the boss's brief: no
     * invented staff), and reviews are real customers' words — the business's own approved reviews fill that section (below).
     * The owner's own section of either kind is kept as it is.
     */
    private const OWNER_ONLY_ON_TEMPLATE = ['team', 'reviews_strip'];

    public const PAGE_PURPOSES = [
        'home' => 'the home page: the whole business at a glance — a strong hero, the main services, why choose us, a few reviews if real ones are given, and a call to action',
        'services' => 'the services page: every service in detail with prices where given, how it works, and a call to action',
        'about' => 'the about page: the business\'s story, the team if given, why choose us, reviews if real ones are given, and the area served',
        'contact' => 'the contact page: how to get in touch, the area served, booking if offered, and short questions and answers',
    ];

    public function __construct(
        private readonly AiRouter $router,
        private readonly DefaultsRegistry $registry,
        private readonly StatedFacts $statedFacts,
        private readonly CompetitorDigest $peers,
        private readonly SiteImageGenerateAction $picture,
        private readonly SiteStockPhotoAction $stock,
        private readonly PriceBook $priceBook,
        private readonly SiteBlockRenderer $renderer,
    ) {}

    /**
     * @return array{status: string, reason?: string, model?: string}
     */
    public function handle(int $businessId, int $pageId, string $engine = 'claude', bool $keepSiteTheme = false): array
    {
        $page = Page::where('business_id', $businessId)->findOrFail($pageId);
        $model = SiteDesignEngines::model($engine);
        if ($model === null) {
            return ['status' => 'refused', 'reason' => 'unknown_engine'];
        }
        $current = is_array($page->draft_blocks) ? $page->draft_blocks : [];

        // Words only: file paths and bookkeeping are taken out before the model sees the page.
        $content = [];
        $oldHero = null;
        foreach ($current as $block) {
            if (! is_array($block)) {
                continue;
            }
            if (($block['type'] ?? '') === 'hero' && $oldHero === null) {
                $oldHero = $block;
            }
            unset($block['image_path'], $block['image_width'], $block['image_height'], $block['source'], $block['model'], $block['peers']);
            if (is_array($block['items'] ?? null)) {
                $items = [];
                foreach ($block['items'] as $item) {
                    if (is_array($item)) {
                        unset($item['image_path'], $item['width'], $item['height']);
                        $items[] = $item;
                    }
                }
                $block['items'] = $items;
            }
            $content[] = $block;
        }

        $prices = [];
        foreach ($this->priceBook->list()->entries as $entry) {
            if ($entry->isConfirmed()) {
                $priceText = PlanPricing::format($entry->amount());
                if ($entry->isRange()) {
                    $priceText .= ' - '.PlanPricing::format($entry->upperAmount());
                }
                $prices[] = "Service: {$entry->label} (Price: {$priceText})";
            }
        }

        $crawled = '';
        foreach (SiteInventoryPage::where('business_id', $businessId)->orderBy('id')->limit(8)->get() as $inventoryPage) {
            $text = trim((string) $inventoryPage->text);
            if ($text !== '') {
                $crawled .= "\n--- ".(string) $inventoryPage->url."\n".mb_substr($text, 0, 1500);
            }
        }
        $crawled = mb_substr($crawled, 0, 9000);
        // The business's own photos from its old website, offered by number: the AI places one only when it truly shows this
        // business (the boss, 2026-10-03: "why does this still look horrible" — no page had a single picture). Logos, icons and
        // small or extreme-shaped images are left out.
        $photoCandidates = SiteInventoryImage::where('business_id', $businessId)->where('status', 'stored')
            ->where('width', '>=', 600)->where('height', '>=', 300)
            ->orderByRaw('width * height desc')->limit(24)->get(['path', 'alt', 'width', 'height'])
            ->filter(fn ($i) => ! preg_match('/logo|icon|badge|favicon|sprite/i', ((string) $i->alt).' '.((string) $i->path))
                && $i->width / max(1, $i->height) >= 0.6 && $i->width / max(1, $i->height) <= 2.5)
            ->values()->all();
        $ownerPhotos = array_slice($photoCandidates, 0, 6);
        $photoLines = [];
        foreach ($ownerPhotos as $n => $photo) {
            $photoLines[] = ($n + 1).'. "'.mb_substr(trim((string) $photo->alt) !== '' ? (string) $photo->alt : 'no description', 0, 120).'" ('.$photo->width.'×'.$photo->height.')';
        }
        // The colours and fonts found on the current website (SiteBrandSignals), so the new site still looks like this business.
        $oldBrand = SiteInventoryPage::where('business_id', $businessId)->whereNotNull('brand')->orderBy('id')->first()?->brand;

        $themes = [];
        foreach (SiteThemes::THEMES as $id => $theme) {
            $themes[] = $id.' — '.$theme['label'].' (best for: '.$theme['for'].')';
        }
        $tokens = app(IndustryStartingPoints::class)->forBusiness($businessId);
        // A site on a template (SiteTemplates): the AI fills that template's sections with words and chooses no look — the
        // template's layout, colours and fonts stay exactly as they are (the boss's brief, 2026-10-04).
        $template = SiteTemplates::get($tokens['template'] ?? null);
        $templateSections = $template === null ? [] : array_values(array_diff(array_intersect($template['sections'], BlockPatchSchema::ADDABLE_TYPES), self::OWNER_ONLY_ON_TEMPLATE));
        // A summary of what the top local peers cover, never their own sentences (CompetitorDigest).
        $peerNotes = $this->peers->block($businessId);
        // Building a whole site: the first page chose the theme; every later page keeps it so the site looks like one brand.
        $siteTheme = $keepSiteTheme && is_string($tokens['theme'] ?? null) && SiteThemes::get($tokens['theme']) !== null ? $tokens['theme'] : null;
        $purpose = self::PAGE_PURPOSES[(string) $page->slug] ?? null;

        $prompt = $this->statedFacts->section($businessId)
            ."\n\n".($prices === [] ? 'Prices you may use: none — do not state any price.' : "Prices you may use (never any other price):\n".implode("\n", $prices))
            ."\n\nPage title: ".(string) $page->title
            .($purpose === null ? '' : "\nThis is ".$purpose.'.')
            .($siteTheme === null ? '' : "\nThis site already uses the theme \"".$siteTheme.'". Use it: return "theme": "'.$siteTheme.'" and no "style", so every page looks the same.')
            ."\n\nThis page's current content (JSON):\n".json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            .($oldHero !== null && ! empty($oldHero['image_path']) ? "\n\nThe business already has a main picture; it is kept on the hero." : '')
            .($photoLines === [] ? '' : "\n\nPhotos from the business's own website — place one with \"owner_photo\": <its number> ONLY if it truly shows this business; otherwise describe a new one:\n".implode("\n", $photoLines))
            .($template !== null
                ? "\n\nThis site uses the \"".$template['label'].'" template, a finished page designed for '.$template['for'].'. '
                    // The home page fills the whole template; another page of the site (services, about, contact) uses only the
                    // sections that suit it, so the whole site is not the same page four times.
                    .($purpose !== null && (string) $page->slug !== 'home'
                        ? 'Use only these sections, in this order, and only those that suit this page — always the hero: '.implode(', ', $templateSections).'.'
                        : 'Return these sections, in this order, and no others: '.implode(', ', $templateSections).'.')
                    .' Leave out a stats section unless the facts or the business\'s own website give real numbers. Do not return a "theme", a "style" or any "variant": the template sets the whole look.'
                : "\n\nThemes (use one id):\n".implode("\n", $themes))
            ."\n\nSection types and their fields: ".self::CATALOGUE
            .($template !== null ? '' : "\n\nFonts you may use: ".implode(' | ', self::modernFontStacks())
            ."\n\nThe brand's current colours and fonts (JSON): ".json_encode(['palette' => $tokens['palette'] ?? [], 'type_pairing' => $tokens['type_pairing'] ?? []], JSON_UNESCAPED_SLASHES))
            .($template !== null || $siteTheme !== null || ! is_array($oldBrand) || $oldBrand === [] ? '' : "\n\nColours and fonts on the business's current website — keep the brand recognisable: use these colours for primary and accent where text stays easy to read, and choose the listed font closest to theirs (JSON): ".json_encode($oldBrand, JSON_UNESCAPED_SLASHES))
            .($crawled === '' ? '' : "\n\nText from the business's current website, for reference only:".$crawled)
            .($peerNotes === '' ? '' : "\n\n".$peerNotes);

        // The chosen AI first. If it fails or its answer is unusable, one retry on a second AI (the boss's plan: "a
        // structural fallback for strict JSON") — the design records which model made it and what it fell back from.
        $attempts = [$model];
        $fallback = SiteDesignEngines::fallbackFor($model);
        if ($fallback !== $model) {
            $attempts[] = $fallback;
        }
        $firstReason = null;
        $json = null;
        $blocks = [];
        $response = null;
        foreach ($attempts as $attemptModel) {
            $response = $this->router->dispatch(new AiRequest(
                task: AiTask::SiteDesign,
                prompt: $prompt,
                system: $this->registry->string('sites.design.system_prompt'),
                model: $attemptModel,
            ));
            $reason = null;
            if (! $response->isUsable() || ! is_string($response->text)) {
                $reason = (string) ($response->failureReason ?? $response->refusalCategory ?? 'unknown');
            } else {
                $json = self::decode($response->text);
                if ($json === null) {
                    $reason = 'not_json';
                } else {
                    $blocks = $this->sections($json, $response->model->value, $current);
                    if ($blocks === []) {
                        $reason = 'no_valid_blocks';
                    }
                }
            }
            if ($reason === null) {
                break;
            }
            $firstReason ??= $reason;
            $json = null;
            $blocks = [];
        }
        if ($json === null || $blocks === []) {
            return $this->fail($page, $engine, (string) $firstReason);
        }

        $theme = is_string($json['theme'] ?? null) && SiteThemes::get($json['theme']) !== null ? $json['theme'] : null;
        if ($siteTheme !== null) {
            $theme = $siteTheme;
        }
        if ($template !== null) {
            $theme = null;
            $sections = $template['sections'];
            $blocks = array_values(array_filter($blocks, static fn (array $b): bool => in_array($b['type'], $sections, true) && ! in_array($b['type'], self::OWNER_ONLY_ON_TEMPLATE, true)));
            // The sections the AI may not write (its products, its photos, a form, the owner's team) stay as the owner has them.
            $kept = array_column($blocks, 'type');
            foreach ($current as $block) {
                $type = is_array($block) ? ($block['type'] ?? null) : null;
                if (is_string($type) && in_array($type, $sections, true) && ! in_array($type, $kept, true)
                    && (! in_array($type, BlockPatchSchema::ADDABLE_TYPES, true) || in_array($type, self::OWNER_ONLY_ON_TEMPLATE, true))) {
                    $blocks[] = $block;
                    $kept[] = $type;
                }
            }
        }
        // The owner's own contact details the AI may not write — opening hours and the facts the owner stated (licence,
        // insurance, area served) — stay on the contact section; an AI design used to drop them.
        $ownerContact = null;
        foreach ($current as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'contact') {
                $ownerContact = $block;
                break;
            }
        }
        if ($ownerContact !== null) {
            foreach ($blocks as $i => $block) {
                if ($block['type'] === 'contact') {
                    foreach (['hours', 'facts', 'industry_facts'] as $key) {
                        if (isset($ownerContact[$key]) && is_array($ownerContact[$key]) && ! isset($block[$key])) {
                            $blocks[$i][$key] = $ownerContact[$key];
                        }
                    }
                }
            }
        }

        $style = null;
        if ($siteTheme === null && $template === null && is_array($json['style'] ?? null) && $json['style'] !== []) {
            $base = $theme !== null
                ? ['palette' => SiteThemes::THEMES[$theme]['palette'], 'type_pairing' => SiteThemes::THEMES[$theme]['type_pairing']]
                : ['palette' => $tokens['palette'] ?? [], 'type_pairing' => $tokens['type_pairing'] ?? []];
            $validation = SiteStyle::validate($json['style'], $base);
            if ($validation['ok']) {
                $style = $validation['style'];
            }
        }
        // Only modern fonts from the AI: an old system font it still names (three of four designs chose Trebuchet + Tahoma on
        // production, 2026-10-04) is dropped, so the theme's own modern pair stands.
        if (is_array($style) && is_array($style['type_pairing'] ?? null)) {
            foreach ($style['type_pairing'] as $role => $stack) {
                if (! isset(SiteFonts::FAMILIES[trim(explode(',', (string) $stack)[0])])) {
                    unset($style['type_pairing'][$role]);
                }
            }
            if ($style['type_pairing'] === []) {
                unset($style['type_pairing']);
            }
            if ($style === []) {
                $style = null;
            }
        }

        // The hero keeps the owner's own picture. Up to three new ones are made, each for a hero, about section or cta_band
        // that has none (the boss, 2026-10-02: "order up to 3 realistic photos per page").
        $heroIndex = null;
        foreach ($blocks as $i => $block) {
            if ($block['type'] === 'hero') {
                $heroIndex = $i;
                break;
            }
        }
        if ($heroIndex !== null && $oldHero !== null && ! empty($oldHero['image_path'])) {
            $blocks[$heroIndex]['image_path'] = $oldHero['image_path'];
            foreach (['image_alt', 'image_width', 'image_height'] as $key) {
                if (isset($oldHero[$key])) {
                    $blocks[$heroIndex][$key] = $oldHero[$key];
                }
            }
        }
        $made = 0;
        $usedPhotos = [];
        foreach (is_array($json['images'] ?? null) ? $json['images'] : [] as $wanted) {
            $index = is_array($wanted) && is_int($wanted['block_index'] ?? null) ? $wanted['block_index'] : null;
            if ($index === null || ! isset($blocks[$index])
                || ! in_array($blocks[$index]['type'], ['hero', 'about', 'cta_band'], true) || ! empty($blocks[$index]['image_path'])) {
                continue;
            }
            // One of the business's own photos, by its number in the list the AI was given; each is used once.
            $photoNumber = is_int($wanted['owner_photo'] ?? null) ? $wanted['owner_photo'] : null;
            if ($photoNumber !== null) {
                $photo = $ownerPhotos[$photoNumber - 1] ?? null;
                if ($photo !== null && ! isset($usedPhotos[$photoNumber])) {
                    $usedPhotos[$photoNumber] = true;
                    $blocks[$index]['image_path'] = (string) $photo->path;
                    $blocks[$index]['image_alt'] = mb_substr(trim((string) $photo->alt) !== '' ? (string) $photo->alt : (string) ($blocks[$index]['headline'] ?? $blocks[$index]['heading'] ?? ''), 0, 120);
                    $blocks[$index]['image_width'] = (int) $photo->width;
                    $blocks[$index]['image_height'] = (int) $photo->height;
                }

                continue;
            }
            if ($made >= 3) {
                continue;
            }
            // On a template the banner tries a stock photo before a picture is made (below), so the AI's idea for it waits.
            if ($template !== null && $blocks[$index]['type'] === 'hero' && $this->stock->available()) {
                continue;
            }
            $description = is_array($wanted) && is_string($wanted['description'] ?? null) ? trim($wanted['description']) : '';
            if ($description === '') {
                continue;
            }
            $made++;
            try {
                $picture = $this->picture->handle($businessId, $description);
            } catch (Throwable) {
                $picture = ['status' => 'failed'];
            }
            if (($picture['status'] ?? null) === 'generated' && is_string($picture['path'] ?? null)) {
                $blocks[$index]['image_path'] = $picture['path'];
                $blocks[$index]['image_alt'] = mb_substr($description, 0, 120);
            }
        }

        // On a template, the business's own photos come first (the boss's brief, 2026-10-04: real photos, never a made-up one
        // passed off as the business): a banner or about section the AI left without a picture gets the owner's next best photo,
        // and a template with a gallery gets one from the photos left — at least three, at most eight. A picture is made only
        // when the business has no usable photo of its own.
        if ($template !== null) {
            $usedPaths = [];
            foreach ($blocks as $block) {
                if (! empty($block['image_path'])) {
                    $usedPaths[(string) $block['image_path']] = true;
                }
            }
            $unused = array_values(array_filter($photoCandidates, static fn ($p): bool => ! isset($usedPaths[(string) $p->path])));
            foreach ($blocks as $i => $block) {
                if (! in_array($block['type'], ['hero', 'about'], true) || ! empty($block['image_path'])) {
                    continue;
                }
                // The banner wants a wide photo; take the first that is at least a little wider than tall.
                $pick = null;
                foreach ($unused as $n => $photo) {
                    if ($block['type'] !== 'hero' || $photo->width >= $photo->height * 1.2) {
                        $pick = $n;
                        break;
                    }
                }
                if ($pick === null) {
                    continue;
                }
                $photo = $unused[$pick];
                array_splice($unused, $pick, 1);
                $blocks[$i]['image_path'] = (string) $photo->path;
                $blocks[$i]['image_alt'] = mb_substr(trim((string) $photo->alt) !== '' ? (string) $photo->alt : (string) ($block['headline'] ?? $block['heading'] ?? ''), 0, 120);
                $blocks[$i]['image_width'] = (int) $photo->width;
                $blocks[$i]['image_height'] = (int) $photo->height;
            }
            if (in_array('gallery', $template['sections'], true) && ! in_array('gallery', array_column($blocks, 'type'), true) && count($unused) >= 3) {
                $items = [];
                foreach (array_slice($unused, 0, 8) as $photo) {
                    $items[] = ['image_path' => (string) $photo->path, 'alt' => mb_substr(trim((string) $photo->alt), 0, 120), 'width' => (int) $photo->width, 'height' => (int) $photo->height];
                }
                $blocks[] = ['type' => 'gallery', 'items' => $items, 'source' => 'owner_photos'];
            }
        }

        // A template with a shop gets the business's real products from its own online store (SiteProductSignals, read by the
        // crawl) — never products or prices from the AI. Each keeps its own page as its link and, when the crawl stored its
        // picture, that picture. At most twelve, in the order the site lists them.
        if ($template !== null && in_array('products', $template['sections'], true) && ! in_array('products', array_column($blocks, 'type'), true)) {
            $items = [];
            $seenNames = [];
            foreach (SiteInventoryPage::where('business_id', $businessId)->whereNotNull('products')->orderBy('id')->get(['products']) as $inventoryPage) {
                foreach (is_array($inventoryPage->products) ? $inventoryPage->products : [] as $found) {
                    $name = is_array($found) && is_string($found['name'] ?? null) ? trim($found['name']) : '';
                    if ($name === '' || isset($seenNames[mb_strtolower($name)])) {
                        continue;
                    }
                    $seenNames[mb_strtolower($name)] = true;
                    $item = ['name' => $name];
                    foreach (['price_text', 'description', 'url'] as $key) {
                        if (is_string($found[$key] ?? null) && trim($found[$key]) !== '') {
                            $item[$key] = trim($found[$key]);
                        }
                    }
                    if (is_string($found['image_url'] ?? null)) {
                        $stored = SiteInventoryImage::where('business_id', $businessId)->where('source_url', $found['image_url'])
                            ->where('status', 'stored')->value('path');
                        if (is_string($stored) && $stored !== '') {
                            $item['image_path'] = $stored;
                            $item['image_alt'] = $name;
                        }
                    }
                    $items[] = $item;
                    if (count($items) >= 12) {
                        break 2;
                    }
                }
            }
            if ($items !== []) {
                $blocks[] = ['type' => 'products', 'items' => $items, 'source' => 'website'];
            }
        }

        // On a template, a banner the business has no wide photo for gets a stock photo of its trade before any picture is made
        // (the boss's brief: the business's own photos first, a stock photo next, a made-up one last). It is marked as stock, and
        // its alt text says so, so it is never taken for the business's own.
        if ($template !== null && $heroIndex !== null && empty($blocks[$heroIndex]['image_path'])) {
            try {
                $stock = $this->stock->handle($businessId, (string) $template['id']);
            } catch (Throwable) {
                $stock = ['status' => 'none'];
            }
            if (($stock['status'] ?? null) === 'stored') {
                $blocks[$heroIndex]['image_path'] = $stock['path'];
                $blocks[$heroIndex]['image_alt'] = $stock['alt'];
                $blocks[$heroIndex]['image_width'] = $stock['width'];
                $blocks[$heroIndex]['image_height'] = $stock['height'];
                $blocks[$heroIndex]['image_source'] = 'stock';
            }
        }

        // A template's reviews are the business's real ones: the reviews the owner ticked for the website, through the same
        // moderation gate the public reviews widget applies (Review::displayable). With none, the owner's own reviews section
        // stays as it was; with neither, the template simply does not draw one.
        if ($template !== null && in_array('reviews_strip', $template['sections'], true)) {
            $items = [];
            $real = Review::query()->displayable()->where('business_id', $businessId)->where('display_on_website', true)
                ->where('rating', '>=', $this->registry->int('sites.draft.reviews_min_rating'))
                ->latest('id')->take($this->registry->int('sites.draft.reviews_max'))->get();
            foreach ($real as $review) {
                $text = trim((string) $review->comment);
                if ($text !== '') {
                    $items[] = ['rating' => $review->rating, 'text' => $text, 'author' => trim((string) $review->reviewer_name) !== '' ? (string) $review->reviewer_name : 'Customer'];
                }
            }
            if ($items !== []) {
                $at = array_search('reviews_strip', array_column($blocks, 'type'), true);
                $strip = ['type' => 'reviews_strip', 'items' => $items, 'source' => 'reviews'];
                if ($at === false) {
                    $blocks[] = $strip;
                } else {
                    if (is_string($blocks[$at]['heading'] ?? null) && trim($blocks[$at]['heading']) !== '') {
                        $strip['heading'] = $blocks[$at]['heading'];
                    }
                    $blocks[$at] = $strip;
                }
            }
        }

        // The top banner is never left without a picture: when neither the AI nor the business supplied one, one is made from
        // the banner's own words. A banner with a picture sits beside its words ("split") rather than a centred column of text.
        if ($heroIndex !== null && empty($blocks[$heroIndex]['image_path']) && $made < 3) {
            $heroWords = trim(((string) ($blocks[$heroIndex]['headline'] ?? '')).'. '.((string) ($blocks[$heroIndex]['subline'] ?? '')), ' .');
            $description = 'A realistic, bright, professional photo for the website of '.((string) Business::whereKey($businessId)->value('name'))
                .($heroWords !== '' ? ': '.$heroWords : '').'. Real people, products or place, natural light, no text, no logos.';
            try {
                $picture = $this->picture->handle($businessId, $description);
            } catch (Throwable) {
                $picture = ['status' => 'failed'];
            }
            if (($picture['status'] ?? null) === 'generated' && is_string($picture['path'] ?? null)) {
                $blocks[$heroIndex]['image_path'] = $picture['path'];
                $blocks[$heroIndex]['image_alt'] = mb_substr($heroWords !== '' ? $heroWords : $description, 0, 120);
            }
        }
        if ($heroIndex !== null && ! empty($blocks[$heroIndex]['image_path'])
            && ! in_array($blocks[$heroIndex]['variant'] ?? null, ['split', 'cover'], true)) {
            $blocks[$heroIndex]['variant'] = 'split';
        }

        // A number the AI states must be the business's own (the boss's brief: no invented years in business or review counts): a
        // stats item is kept only when every number in it appears in the owner's stated facts, the business's own website or the
        // page as it stands; one with no number at all is a claim nobody stated, so it goes too. A stats section left empty is
        // dropped. Done last, after every picture is placed, so no block index the AI named moves under it.
        $blocks = self::onlySupportedStats($blocks, $this->statedFacts->section($businessId)."\n".$crawled."\n"
            .json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $page->refresh();
        $meta = $page->draft_meta ?? [];
        $meta['designs'][$engine] = [
            'status' => 'ready',
            'theme' => $theme,
            'template' => $template === null ? null : $template['id'],
            'style' => $style,
            'blocks' => $blocks,
            'explanation' => is_string($json['explanation'] ?? null) ? mb_substr($json['explanation'], 0, 600) : '',
            'model' => $response->model->value,
            'fallback_from' => $response->model === $model ? null : $model->value,
            'drafted_at' => now()->toIso8601String(),
        ];
        $page->draft_meta = $meta;
        $page->save();

        return ['status' => 'ready', 'model' => $response->model->value];
    }

    /**
     * The AI's sections, each cleaned by the same rules as every AI edit; anything else is dropped.
     *
     * @param  array<string, mixed>  $json
     * @param  array<int, mixed>  $current
     * @return list<array<string, mixed>>
     */
    private function sections(array $json, string $modelValue, array $current): array
    {
        $blocks = [];
        foreach (is_array($json['blocks'] ?? null) ? $json['blocks'] : [] as $block) {
            $type = is_array($block) ? ($block['type'] ?? null) : null;
            if (! is_string($type) || ! in_array($type, BlockPatchSchema::ADDABLE_TYPES, true)) {
                continue;
            }
            // A video only with an address already on the page: the designer has no words from the owner to take one from.
            if ($type === 'video_embed' && ! BlockPatchSchema::ownerGaveVideo($block['contentUrl'] ?? null, '', $current)) {
                continue;
            }
            $clean = BlockPatchSchema::modelBlock($type, $block, $block['items'] ?? null);
            if (BlockPatchSchema::hasContent($clean) && $this->renderer->isValidBlock($clean)) {
                $clean['source'] = 'ai';
                $clean['model'] = $modelValue;
                $blocks[] = $clean;
            }
        }

        return $blocks;
    }

    /**
     * @return array{status: string, reason: string}
     */
    public function fail(Page $page, string $engine, string $reason): array
    {
        $page->refresh();
        $meta = $page->draft_meta ?? [];
        $meta['designs'][$engine] = ['status' => 'failed', 'reason' => $reason, 'failed_at' => now()->toIso8601String()];
        $page->draft_meta = $meta;
        $page->save();

        return ['status' => 'failed', 'reason' => $reason];
    }

    /**
     * The JSON object in an answer, tolerating a ```json fence or a sentence around it.
     *
     * @return array<string, mixed>|null
     */
    public static function decode(string $text): ?array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end < $start) {
            return null;
        }
        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * The blocks with every stats item whose numbers are not all in the evidence removed, and an emptied stats block dropped.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    private static function onlySupportedStats(array $blocks, string $evidence): array
    {
        $kept = [];
        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'stats') {
                $items = array_values(array_filter(is_array($block['items'] ?? null) ? $block['items'] : [], static function (mixed $item) use ($evidence): bool {
                    $value = is_array($item) && is_scalar($item['value'] ?? null) ? (string) $item['value'] : '';
                    if (preg_match_all('/\d+(?:[.,]\d+)?/', $value, $numbers) < 1) {
                        return false;
                    }
                    foreach ($numbers[0] as $number) {
                        if (preg_match('/(?<![\d.,])'.preg_quote($number, '/').'(?![\d]|[.,]\d)/', $evidence) !== 1) {
                            return false;
                        }
                    }

                    return true;
                }));
                if ($items === []) {
                    continue;
                }
                $block['items'] = $items;
            }
            $kept[] = $block;
        }

        return $kept;
    }

    /**
     * The font stacks the AI is offered: only those led by a modern family (SiteFonts), never the old system stacks.
     *
     * @return list<string>
     */
    private static function modernFontStacks(): array
    {
        return array_values(array_filter(SiteStyle::FONT_STACKS, fn (string $stack) => isset(SiteFonts::FAMILIES[trim(explode(',', $stack)[0])])));
    }
}
