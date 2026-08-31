<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\IndustryFamily;
use App\Models\IndustryPage;
use App\Services\Industries\IndustryDemoDoors;
use App\Services\Industries\IndustryPages;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * The hundred industry pages and their hub — CC-3 §3.
 *
 * Not Livewire, and `MarketingController`'s docblock has the whole argument: these
 * pages have no server-held state worth a round trip, and shipping a component
 * runtime to a page that has no component is the cheapest way to lose the LCP
 * budget. This is the same surface one directory along.
 *
 * ⚠️ **A CONTROLLER OF ITS OWN RATHER THAN TWO MORE METHODS ON
 * `MarketingController`, AND THAT IS A BUILD DECISION RATHER THAN A DESIGN ONE.**
 * CC-2 and CC-3 are being built on two branches at once and both touch this
 * surface; one file edited by both is a merge conflict in the middle of a
 * controller, where a bad resolution is a 500 on a public page. Decision 5227.
 *
 * ⛔ **`index()` IS CC-2's STUB, BUILT HERE BECAUSE CC-2 IS NOT ON THIS BRANCH.**
 * The route name `industries.index` is the seam the two slices agreed on, and it
 * is identical on both sides — so whichever lands second, the merge keeps one
 * implementation and every `route('industries.index')` in either slice resolves.
 * The hub header below is CC-2 §2.11's VERBATIM copy, reproduced rather than
 * invented for the same reason.
 */
final class IndustryPageController extends Controller
{
    /**
     * The hub — PIII-72 §A1.
     */
    public function index(IndustryPages $pages, IndustryDemoDoors $doors): View
    {
        $sections = $pages->hub();

        return view('marketing.industries.index', [
            'sections' => $sections,
            'families' => array_map(
                static fn (string $family): IndustryFamily => IndustryFamily::from($family),
                array_keys($sections),
            ),
            // ⚠️ THE HUB'S OWN INDEXABILITY IS DERIVED FROM THE SAME COLUMN as
            // every page's, and from the rows already loaded rather than a
            // second query. A hub that invited crawling while all hundred of its
            // links said `noindex` would be a hundred internal links to pages a
            // crawler has been told to ignore — and a second flag for the hub
            // would be a second thing that can disagree with `index_mode`
            // (decision 5225).
            'noindex' => ! collect($sections)->flatten()->contains(
                static fn (IndustryPage $page): bool => $page->index_mode,
            ),
            'demoNumber' => $doors->number(),
            'doors' => $doors,
        ]);
    }

    /**
     * One industry lander, or the 301 its slug used to point at.
     *
     * ⚠️ **THE ORDER IS LOOKUP → REDIRECT → 404 AND IT IS LOAD-BEARING.** Asking
     * the history first would let a row renamed back to an old address of its own
     * redirect to itself; the model's hook prunes the current slug from its own
     * history for the same reason, and either guard alone leaves the loop
     * reachable (decision 5226).
     */
    public function show(string $slug, IndustryPages $pages, IndustryDemoDoors $doors): View|RedirectResponse
    {
        $page = $pages->findBySlug($slug);

        if ($page === null) {
            $moved = $pages->redirectTargetFor($slug);

            abort_if($moved === null, 404);

            // 301 rather than 302: PIII-72 §A4's law is that a moved page never
            // becomes a dead page, and a temporary redirect asks a crawler to
            // keep the old URL indexed for ever.
            return redirect()->route('industries.show', $moved->slug, 301);
        }

        return view('marketing.industries.show', [
            'page' => $page,
            'siblings' => $pages->siblingsFor($page),
            'demoNumber' => $doors->number(),
            'breadcrumbs' => self::breadcrumbsFor($page),
        ]);
    }

    /**
     * The BreadcrumbList this page carries — PIII-72 §A2.5's *"Home › Industries
     * › [Industry], with BreadcrumbList schema riding M8 (switchless, like the
     * rest)"*.
     *
     * ⚠️ **BUILT HERE RATHER THAN IN THE TEMPLATE, AND NOT FOR TIDINESS.**
     * `@json([...])` spelled inline in Blade is a directive whose argument is
     * parsed before PHP ever sees it, and a multi-line array literal fails that
     * parse with *"Unclosed '[' does not match ')'"* — a 500 on a public page,
     * from a template that reads perfectly. One variable is what the directive
     * is for.
     *
     * @return array<string, mixed>
     */
    private static function breadcrumbsFor(IndustryPage $page): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Industries', 'item' => route('industries.index')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $page->noun(), 'item' => route('industries.show', $page->slug)],
            ],
        ];
    }
}
