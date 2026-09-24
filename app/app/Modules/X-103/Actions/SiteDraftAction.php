<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Models\Business;
use App\Models\Location;
use App\Models\Review;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\SiteInventoryImage;
use App\Modules\X103\Models\SiteInventoryPage;
use App\Modules\X113\Actions\StaffRosterAction;
use App\Modules\X155\Actions\FormReadAction;
use App\Services\Assistant\PriceBook;
use App\Services\Config\DefaultsRegistry;
use App\Services\Facts\BusinessFactKey;
use App\Services\Facts\BusinessFacts;
use App\Services\Links\TenantLinks;
use App\Support\PlanPricing;
use Illuminate\Support\Str;

final class SiteDraftAction
{
    public function __construct(
        private readonly PriceBook $priceBook,
        private readonly TenantLinks $tenantLinks,
        private readonly DefaultsRegistry $registry,
        private readonly BusinessFacts $facts
    ) {}

    public function handle(int $businessId, int $locationId): array
    {
        $business = Business::find($businessId);
        $location = Location::find($locationId);
        $stated = $this->facts->all($businessId);

        $pagesCreated = 0;
        $blocksGenerated = 0;
        $skipped = [];
        $sourcesUsed = [];
        $sourcesWithoutData = [];

        // Fetch Inventory
        $inventoryPages = SiteInventoryPage::where('business_id', $businessId)->get();

        $homeInventoryPage = null;
        if ($location && $location->website_url) {
            $homeInventoryPage = $inventoryPages->firstWhere('url', $location->website_url);
        }
        if (! $homeInventoryPage && $inventoryPages->isNotEmpty()) {
            $homeInventoryPage = $inventoryPages->first();
        }

        $longestInventoryPage = $inventoryPages->sortByDesc(function ($page) {
            return strlen($page->text ?? '');
        })->first();

        $firstImage = SiteInventoryImage::where('business_id', $businessId)
            ->where('status', 'stored')
            ->first();

        $aboutMaxChars = $this->registry->int('sites.draft.about_max_chars');
        $reviewsMax = $this->registry->int('sites.draft.reviews_max');
        $reviewsMinRating = $this->registry->int('sites.draft.reviews_min_rating');
        $galleryMax = $this->registry->int('sites.draft.gallery_max');
        $teamMin = $this->registry->int('sites.draft.team_min');

        // Existing FAQs
        $allPages = Page::where('business_id', $businessId)->get();
        $faqBlocks = [];
        foreach ($allPages as $p) {
            $blocks = $p->draft_blocks;
            if (is_array($blocks)) {
                foreach ($blocks as $block) {
                    if (($block['type'] ?? '') === 'faq') {
                        $block['source'] = 'inventory';
                        $faqBlocks[] = $block;
                    }
                }
            }
        }

        $address = $location ? $location->address : null;
        $contactPhone = null;
        $contactEmail = null;
        $contactPhoneSource = '';
        $contactEmailSource = '';

        if ($homeInventoryPage) {
            $phones = $homeInventoryPage->phones ?? [];
            $emails = $homeInventoryPage->emails ?? [];
            if (! empty($phones)) {
                $contactPhone = $phones[0];
                $contactPhoneSource = 'inventory';
            }
            if (! empty($emails)) {
                $contactEmail = $emails[0];
                $contactEmailSource = 'inventory';
            }
        }
        if (! $contactPhone && $business && $business->phone) {
            $contactPhone = $business->phone;
            $contactPhoneSource = 'business';
        }

        $buildContactBlock = function () use ($location, $address, $contactPhone, $contactPhoneSource, $contactEmail, $contactEmailSource, &$blocksGenerated, &$sourcesUsed, $stated) {
            $contactSource = 'location';
            if ($contactPhone) {
                $contactSource .= ", phone: {$contactPhoneSource}";
            }
            if ($contactEmail) {
                $contactSource .= ", email: {$contactEmailSource}";
            }

            $block = [
                'type' => 'contact',
                'source' => $contactSource,
            ];
            if ($address) {
                $block['address'] = $address;
            }
            if ($contactPhone) {
                $block['phone'] = $contactPhone;
            }
            if ($contactEmail) {
                $block['email'] = $contactEmail;
            }
            if ($location !== null && is_array($location->opening_hours) && $location->opening_hours !== []) {
                $block['hours'] = $location->opening_hours;
                $contactSource .= ', hours: location';
                $block['source'] = $contactSource;
            }

            $contactFacts = [];
            foreach ([BusinessFactKey::LICENCE_NUMBER, BusinessFactKey::INSURANCE, BusinessFactKey::SERVICE_AREA, BusinessFactKey::YEARS_IN_BUSINESS] as $fKey) {
                if (($stated[$fKey] ?? '') !== '') {
                    $contactFacts[$fKey] = $stated[$fKey];
                }
            }
            if ($contactFacts !== []) {
                $block['facts'] = $contactFacts;
                $contactSource .= ', facts: owner';
                $block['source'] = $contactSource;
            }
            $blocksGenerated++;
            $sourcesUsed[] = $contactSource;

            return $block;
        };

        $buildFormBlock = function () use ($businessId, &$blocksGenerated, &$sourcesUsed, &$sourcesWithoutData) {
            $definition = (new FormReadAction)->firstDefinitionForBusiness($businessId);
            if ($definition) {
                $blocksGenerated++;
                $sourcesUsed[] = 'forms';

                return [
                    'type' => 'form',
                    'source' => 'forms',
                    'definition_id' => $definition['id'],
                    'fields' => $definition['fields'],
                    'required' => $definition['required'],
                    'honeypot' => $definition['honeypot'],
                ];
            }
            $sourcesWithoutData[] = 'forms';

            return null;
        };

        $buildServicesBlock = function () use (&$blocksGenerated, &$sourcesUsed) {
            $priceList = $this->priceBook->list();
            if (! $priceList->isEmpty()) {
                $items = [];
                foreach ($priceList->entries as $entry) {
                    if ($entry->isConfirmed()) {
                        $priceText = PlanPricing::format($entry->amount());
                        if ($entry->isRange()) {
                            $priceText .= ' - '.PlanPricing::format($entry->upperAmount());
                        }
                        $items[] = [
                            'name' => $entry->label,
                            'price' => $priceText,
                        ];
                    }
                }
                if (count($items) > 0) {
                    $blocksGenerated++;
                    $sourcesUsed[] = 'pricebook';

                    return [
                        'type' => 'services',
                        'items' => $items,
                        'source' => 'pricebook',
                    ];
                }
            }

            return null;
        };

        // 1. HOME
        if (Page::where('business_id', $businessId)->where('slug', 'home')->exists()) {
            $skipped[] = 'home';
        } else {
            $homeBlocks = [];

            $headline = $business->name;
            if ($homeInventoryPage && ! empty($homeInventoryPage->headings)) {
                $headline = $homeInventoryPage->headings[0];
            }
            $subline = '';
            if ($homeInventoryPage && $homeInventoryPage->text) {
                $subline = Str::limit($homeInventoryPage->text, 160, '');
            }
            $heroSource = 'inventory';
            if ($subline === '' && ($stated[BusinessFactKey::TAGLINE] ?? '') !== '') {
                $subline = $stated[BusinessFactKey::TAGLINE];
                $heroSource = 'inventory, tagline: facts';
            }
            $hero = [
                'type' => 'hero',
                'headline' => $headline,
                'subline' => $subline,
                'source' => $heroSource,
            ];
            if ($firstImage) {
                $hero['image_path'] = $firstImage->path;
                $hero['image_alt'] = (string) ($firstImage->alt ?? '');
            }
            $homeBlocks[] = $hero;
            $blocksGenerated++;
            $sourcesUsed[] = 'inventory';

            if (($stated[BusinessFactKey::DESCRIPTION] ?? '') !== '') {
                $homeBlocks[] = [
                    'type' => 'about',
                    'text' => Str::limit($stated[BusinessFactKey::DESCRIPTION], $aboutMaxChars, ''),
                    'source' => 'facts',
                ];
                $blocksGenerated++;
                $sourcesUsed[] = 'facts';
            } elseif ($longestInventoryPage && $longestInventoryPage->text) {
                $homeBlocks[] = [
                    'type' => 'about',
                    'text' => Str::limit($longestInventoryPage->text, $aboutMaxChars, ''),
                    'source' => 'inventory',
                ];
                $blocksGenerated++;
                $sourcesUsed[] = 'inventory';
            }

            $storedImages = SiteInventoryImage::where('business_id', $businessId)
                ->where('status', 'stored')
                ->orderBy('id')
                ->skip(1)
                ->take($galleryMax)
                ->get();

            if ($storedImages->isNotEmpty()) {
                $galleryItems = [];
                foreach ($storedImages as $img) {
                    $galleryItems[] = [
                        'image_path' => $img->path,
                        'alt' => (string) ($img->alt ?? ''),
                    ];
                }
                $homeBlocks[] = [
                    'type' => 'gallery',
                    'items' => $galleryItems,
                    'source' => 'inventory',
                ];
                $blocksGenerated++;
                $sourcesUsed[] = 'inventory';
            }

            $servicesBlock = $buildServicesBlock();
            if ($servicesBlock) {
                $homeBlocks[] = $servicesBlock;
            }

            // displayable() is the moderation gate — the same one the public widget feed applies; display fails closed.
            $reviews = Review::query()->displayable()->where('location_id', $locationId)
                ->where('display_on_website', true)
                ->where('rating', '>=', $reviewsMinRating)
                ->take($reviewsMax)
                ->get();
            if ($reviews->isNotEmpty()) {
                $items = [];
                foreach ($reviews as $review) {
                    $items[] = [
                        'rating' => $review->rating,
                        'text' => $review->comment,
                        'author' => $review->reviewer_name ?? 'Customer',
                    ];
                }
                $homeBlocks[] = [
                    'type' => 'reviews_strip',
                    'items' => $items,
                    'source' => 'reviews',
                ];
                $blocksGenerated++;
                $sourcesUsed[] = 'reviews';
            }

            $bookingLink = $this->tenantLinks->booking();
            if ($bookingLink) {
                $homeBlocks[] = [
                    'type' => 'booking_button',
                    'label' => 'Book Now',
                    'url' => $bookingLink->destination(),
                    'source' => 'links',
                ];
                $blocksGenerated++;
                $sourcesUsed[] = 'links';
            }

            $firstServiceName = '';
            if ($servicesBlock && count($servicesBlock['items']) > 0) {
                $firstServiceName = $servicesBlock['items'][0]['name'];
            }

            $homeBlocks[] = [
                'type' => 'booking_form',
                'heading' => 'Request a time',
                'label' => 'Request a time',
                'service' => $firstServiceName,
                'source' => 'scheduler',
            ];
            $blocksGenerated++;
            $sourcesUsed[] = 'scheduler';

            foreach ($faqBlocks as $faqBlock) {
                $homeBlocks[] = $faqBlock;
                $blocksGenerated++;
                $sourcesUsed[] = 'inventory'; // already set in block but whatever
            }

            $homeBlocks[] = $buildContactBlock();
            $formBlock = $buildFormBlock();
            if ($formBlock) {
                $homeBlocks[] = $formBlock;
            }

            Page::create([
                'business_id' => $businessId,
                'slug' => 'home',
                'title' => 'Home',
                'is_tenant_edited' => false,
                'is_published' => false,
                'draft_blocks' => $homeBlocks,
            ]);
            $pagesCreated++;
        }

        // ABOUT
        if (Page::where('business_id', $businessId)->where('slug', 'about')->exists()) {
            $skipped[] = 'about';
        } else {
            $staffRoster = (new StaffRosterAction)->handle($businessId);
            if (count($staffRoster) >= $teamMin) {
                $aboutBlocks = [];
                $aboutBlocks[] = [
                    'type' => 'team',
                    'items' => $staffRoster,
                    'source' => 'staff',
                ];
                $blocksGenerated++;
                $sourcesUsed[] = 'staff';

                Page::create([
                    'business_id' => $businessId,
                    'slug' => 'about',
                    'title' => 'About',
                    'is_tenant_edited' => false,
                    'is_published' => false,
                    'draft_blocks' => $aboutBlocks,
                ]);
                $pagesCreated++;
            }
        }

        // 2. SERVICES
        if (Page::where('business_id', $businessId)->where('slug', 'services')->exists()) {
            $skipped[] = 'services';
        } else {
            $servicesPageBlocks = [];
            $servicesBlock = $buildServicesBlock();
            if ($servicesBlock) {
                $servicesPageBlocks[] = $servicesBlock;
            }
            Page::create([
                'business_id' => $businessId,
                'slug' => 'services',
                'title' => 'Services',
                'is_tenant_edited' => false,
                'is_published' => false,
                'draft_blocks' => $servicesPageBlocks,
            ]);
            $pagesCreated++;
        }

        // 3. CONTACT
        if (Page::where('business_id', $businessId)->where('slug', 'contact')->exists()) {
            $skipped[] = 'contact';
        } else {
            $contactBlocks = [$buildContactBlock()];
            $formBlock = $buildFormBlock();
            if ($formBlock) {
                $contactBlocks[] = $formBlock;
            }
            Page::create([
                'business_id' => $businessId,
                'slug' => 'contact',
                'title' => 'Contact',
                'is_tenant_edited' => false,
                'is_published' => false,
                'draft_blocks' => $contactBlocks,
            ]);
            $pagesCreated++;
        }

        return [
            'pages' => $pagesCreated,
            'blocks' => $blocksGenerated,
            'skipped' => $skipped,
            'sources' => array_values(array_unique($sourcesUsed)),
            'sources_without_data' => array_values(array_unique($sourcesWithoutData)),
        ];
    }
}
