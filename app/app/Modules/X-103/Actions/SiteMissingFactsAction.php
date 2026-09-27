<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Models\Location;
use App\Models\Review;
use App\Modules\X103\Models\SiteInventoryImage;
use App\Modules\X113\Actions\StaffRosterAction;
use App\Modules\X155\Actions\FormReadAction;
use App\Services\Assistant\PriceBook;
use App\Services\Config\DefaultsRegistry;
use App\Services\Links\TenantLinks;

/**
 * The facts the site draft cannot find, each with where the owner fills it in.
 *
 * Deterministic, no AI. Every check copies the draft's OWN filter (confirmed
 * non-sample prices, stored images, displayable reviews, active staff), so a
 * fact is "missing" here exactly when SiteDraftAction would leave its block out.
 * A row is ['key' => …, 'label' => …, 'hint' => …, 'route' => route name or null,
 * 'here' => true when the fix is on Your current website itself].
 *
 * @return list<array{key: string, label: string, hint: string, route: ?string, here: bool}>
 */
final class SiteMissingFactsAction
{
    public function __construct(
        private readonly PriceBook $priceBook,
        private readonly TenantLinks $tenantLinks,
        private readonly DefaultsRegistry $registry,
        private readonly StaffRosterAction $staff,
    ) {}

    public function handle(int $businessId, ?Location $location): array
    {
        $rows = [];

        if ($location === null || trim((string) $location->address) === '') {
            $rows[] = ['key' => 'address', 'label' => 'Your address', 'hint' => 'The contact section shows it on every page.', 'route' => 'account.locations', 'here' => false];
        }
        if ($location === null || trim((string) $location->primary_phone) === '') {
            $rows[] = ['key' => 'phone', 'label' => 'Your phone number', 'hint' => 'Visitors call from the contact section.', 'route' => 'account.locations', 'here' => false];
        }
        if ($location === null || ! is_array($location->opening_hours) || $location->opening_hours === []) {
            $rows[] = ['key' => 'hours', 'label' => 'Your opening hours', 'hint' => 'Set them below; the contact section shows them.', 'route' => null, 'here' => true];
        }

        $confirmed = 0;
        foreach ($this->priceBook->list()->entries as $entry) {
            if ($entry->isConfirmed()) {
                $confirmed++;
            }
        }
        if ($confirmed === 0) {
            $rows[] = ['key' => 'services', 'label' => 'Your services and prices', 'hint' => 'Confirmed prices become the services section.', 'route' => 'x-163.pricebook', 'here' => false];
        }

        $stored = SiteInventoryImage::where('business_id', $businessId)->where('status', 'stored')->count();
        if ($stored < 2) {
            $rows[] = ['key' => 'photos', 'label' => 'Photos of your work', 'hint' => 'Crawl your website and copy its images above; the first is the hero, the rest the gallery.', 'route' => null, 'here' => true];
        }

        if (count($this->staff->handle($businessId)) < $this->registry->int('sites.draft.team_min')) {
            $rows[] = ['key' => 'team', 'label' => 'Your team', 'hint' => 'Active staff make the About page.', 'route' => 'x-113.staff', 'here' => false];
        }

        if (empty((new FormReadAction)->firstDefinitionForBusiness($businessId))) {
            $rows[] = ['key' => 'form', 'label' => 'A contact form', 'hint' => 'Without one, visitors cannot write to you from the page.', 'route' => 'x-155.forms', 'here' => false];
        }

        if ($this->tenantLinks->booking() === null) {
            $rows[] = ['key' => 'booking', 'label' => 'A booking link', 'hint' => 'The Book Now button needs somewhere to go.', 'route' => 'account.assistant-links', 'here' => false];
        }

        if ($location !== null) {
            $minRating = $this->registry->int('sites.draft.reviews_min_rating');
            $reviews = Review::query()->displayable()->where('location_id', $location->id)->where('display_on_website', true)->where('rating', '>=', $minRating)->count();
            if ($reviews === 0) {
                $rows[] = ['key' => 'reviews', 'label' => 'Reviews to show', 'hint' => 'Approved reviews you have ticked for the website become the reviews strip.', 'route' => 'account.website', 'here' => false];
            }
        }

        return $rows;
    }
}
