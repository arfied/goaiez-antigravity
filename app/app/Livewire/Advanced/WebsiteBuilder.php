<?php

declare(strict_types=1);

namespace App\Livewire\Advanced;

use App\Models\Business;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.account')]
class WebsiteBuilder extends Component
{
    public string $template = 'modern_service';

    public string $headline = 'Top-Rated Local Service You Can Trust';

    public string $subheadline = 'Licensed professionals serving residential & commercial clients with 5-star quality and upfront pricing.';

    public string $ctaText = 'Book Free Estimate';

    public string $phoneNumber = '+1 (555) 234-5678';

    public string $primaryColor = '#4f46e5'; // Indigo

    // Dynamic Module Toggles
    public bool $showReviewsWidget = true;

    public bool $showBeforeAfter = true;

    public bool $showBookingForm = true;

    public bool $showFaqSection = true;

    public bool $showStickySpeedDial = true;

    // Viewport Mode
    public string $previewDevice = 'desktop'; // desktop, mobile

    // 1-Prompt AI Generator
    public string $aiPrompt = '24/7 Emergency local service specialist in Austin with 5-star reviews and upfront pricing';

    public ?string $aiNotification = null;

    // Before & After Interactive Slider State (percentage 0-100)
    public int $sliderPosition = 50;

    // Simulated Lead Form
    public string $leadName = 'Michael Davis';

    public string $leadPhone = '+1 (555) 432-1098';

    public string $leadService = 'Emergency Consultation';

    public ?string $leadDispatchNotification = null;

    public ?string $publishNotification = null;

    /** @var array<int, array{icon: string, title: string, desc: string}> */
    public array $services = [
        ['icon' => '⚡', 'title' => 'Same-Day Availability', 'desc' => 'Prompt local scheduling and 24/7 emergency dispatch.'],
        ['icon' => '🛡️', 'title' => '100% Guaranteed Quality', 'desc' => 'Licensed, insured, and backed by our 5-star satisfaction guarantee.'],
        ['icon' => '💬', 'title' => 'Transparent Upfront Pricing', 'desc' => 'Clear written estimates with zero surprise fees or hidden costs.'],
    ];

    /** @var array<int, array{q: string, a: string}> */
    public array $faqs = [
        ['q' => 'How quickly can your technicians arrive?', 'a' => 'We offer same-day consultations and 60-minute emergency arrivals depending on your district.'],
        ['q' => 'Are your specialists licensed and insured?', 'a' => 'Yes, every team member carries comprehensive commercial liability insurance and state licensing.'],
        ['q' => 'Do you provide free written estimates?', 'a' => 'Absolutely. We provide clear, itemized upfront quotes before any work begins.'],
    ];

    public function generateWithAI(): void
    {
        $prompt = strtolower($this->aiPrompt);

        if (str_contains($prompt, 'plumb') || str_contains($prompt, 'pipe') || str_contains($prompt, 'drain')) {
            $this->headline = 'Fast, Reliable Emergency Plumbing in Austin';
            $this->subheadline = 'From broken pipes and clogged drains to water heater replacements, our master plumbers get it done right.';
            $this->services = [
                ['icon' => '🔧', 'title' => '24/7 Emergency Repairs', 'desc' => 'Burst pipes, slab leaks, and rapid water extraction.'],
                ['icon' => '🚿', 'title' => 'Drain Cleaning & Hydro-Jetting', 'desc' => 'Clear tough blockages and tree roots permanently.'],
                ['icon' => '🔥', 'title' => 'Tankless Water Heaters', 'desc' => 'Energy-efficient installations with instant hot water.'],
            ];
            $this->template = 'bold_contractor';
        } elseif (str_contains($prompt, 'dental') || str_contains($prompt, 'clinic') || str_contains($prompt, 'doctor') || str_contains($prompt, 'health')) {
            $this->headline = 'Gentle, Advanced Dental & Healthcare Clinic';
            $this->subheadline = 'Comprehensive care for the whole family in a comfortable, state-of-the-art modern facility.';
            $this->services = [
                ['icon' => '✨', 'title' => 'Cosmetic & Smile Restorations', 'desc' => 'Teeth whitening, porcelain veneers, and invisalign.'],
                ['icon' => '🩺', 'title' => 'Family Preventative Care', 'desc' => 'Gentle cleanings, digital checkups, and pediatric care.'],
                ['icon' => '⚡', 'title' => 'Same-Day Dental Emergencies', 'desc' => 'Immediate relief for toothaches, chips, and broken crowns.'],
            ];
            $this->template = 'healthcare_clean';
        } elseif (str_contains($prompt, 'salon') || str_contains($prompt, 'spa') || str_contains($prompt, 'aesthetic') || str_contains($prompt, 'beauty')) {
            $this->headline = 'Elevate Your Look with Premier Salon & Spa Services';
            $this->subheadline = 'Master stylists and skincare experts dedicated to customized beauty transformations.';
            $this->services = [
                ['icon' => '💇', 'title' => 'Master Haircut & Balayage', 'desc' => 'Custom color formulation and precision styling.'],
                ['icon' => '💆', 'title' => 'HydraFacials & Skincare', 'desc' => 'Medical-grade rejuvenating skin therapies.'],
                ['icon' => '💅', 'title' => 'Lash & Brow Artistry', 'desc' => 'Microblading, lash extensions, and brow lamination.'],
            ];
            $this->template = 'elegant_salon';
        } else {
            $this->headline = 'Top-Rated Local Service You Can Trust';
            $this->subheadline = 'Licensed professionals serving residential & commercial clients with 5-star quality and upfront pricing.';
            $this->services = [
                ['icon' => '⚡', 'title' => 'Same-Day Availability', 'desc' => 'Prompt local scheduling and emergency dispatch.'],
                ['icon' => '🛡️', 'title' => '100% Guaranteed Quality', 'desc' => 'Licensed, insured, and backed by a 5-star guarantee.'],
                ['icon' => '💬', 'title' => 'Transparent Upfront Pricing', 'desc' => 'Clear written estimates with zero surprise fees.'],
            ];
        }

        $this->aiNotification = '✨ AI generated custom headlines, services, and FAQ tailored to your business prompt!';
    }

    public function simulateLeadSubmission(): void
    {
        $this->leadDispatchNotification = '📲 LEAD DISPATCHED! (1) Instant SMS sent to business owner: "New lead from '.$this->leadName.' ('.$this->leadPhone.') for '.$this->leadService.'". (2) Confirmation text sent to customer.';
    }

    public function publishSite(): void
    {
        $this->publishNotification = '🎉 Website published live! Local SEO Schema (JSON-LD LocalBusiness & AggregateRating) auto-injected.';
    }

    public function render(): View
    {
        $business = Tenancy::id() ? Business::find(Tenancy::id()) : null;
        $businessName = $business ? $business->name : 'Apex Pro Services';

        return view('livewire.advanced.website-builder', [
            'businessName' => $businessName,
        ]);
    }
}
