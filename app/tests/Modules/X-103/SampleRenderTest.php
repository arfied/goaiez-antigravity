<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Enums\IndustryFamily;
use App\Modules\X103\Domain\SiteBlockRenderer;
use App\Services\Industry\IndustryStartingPoints;
use Tests\TestCase;

class SampleRenderTest extends TestCase
{
    private const CONTEXT = ['businessName' => 'Calder & Sons Plumbing', 'deployHash' => 'weight', 'tenant_storage_url_prefix' => '/m/', 'form_action_base' => '/f'];

    public function test_it_writes_a_sample_page_for_the_owner_to_look_at(): void
    {
        $tokens = app(IndustryStartingPoints::class)->for(IndustryFamily::Trades);

        $blocks = [
            [
                'type' => 'hero',
                'headline' => 'Reliable Plumbing Services When You Need Them',
                'subline' => 'Fast, professional, and guaranteed plumbing repairs for homes and businesses.',
            ],
            [
                'type' => 'booking_button',
                'label' => 'Book a plumber',
                'url' => 'tel:5550198',
            ],
            [
                'type' => 'about',
                'heading' => 'About Calder & Sons',
                'text' => 'With over two decades of experience, we provide top-tier plumbing services with a focus on honesty and quality workmanship.',
            ],
            [
                'type' => 'services',
                'heading' => 'Our Plumbing Services',
                'items' => [
                    [
                        'name' => 'Emergency Repairs',
                        'price_text' => 'From $150',
                        'description' => '24/7 rapid response for bursts, leaks, and urgent blockages.',
                    ],
                    [
                        'name' => 'Drain Cleaning',
                        'price_text' => 'From $95',
                        'description' => 'Advanced routing and jetting to clear stubborn drain clogs.',
                    ],
                    [
                        'name' => 'Water Heater Install',
                        'price_text' => 'Call for quote',
                        'description' => 'Professional installation of tank and tankless water systems.',
                    ],
                    [
                        'name' => 'Fixture Replacement',
                        'price_text' => 'From $120',
                        'description' => 'Upgrades and replacements for sinks, faucets, and toilets.',
                    ],
                ],
            ],
            [
                'type' => 'team',
                'heading' => 'Meet Our Plumbers',
                'items' => [
                    [
                        'name' => 'James Calder',
                        'role' => 'Master Plumber',
                    ],
                    [
                        'name' => 'Sarah Jenkins',
                        'role' => 'Pipe Fitter',
                    ],
                    [
                        'name' => 'Michael Vance',
                        'role' => 'Service Technician',
                    ],
                ],
            ],
            [
                'type' => 'reviews_strip',
                'heading' => 'What Our Customers Say',
                'items' => [
                    [
                        'rating' => '5/5',
                        'text' => 'James fixed our flooded basement in under an hour. Absolute lifesaver!',
                        'author' => 'Emily R.',
                    ],
                    [
                        'rating' => '5/5',
                        'text' => 'Very professional and explained the whole repair process clearly.',
                        'author' => 'David T.',
                    ],
                    [
                        'rating' => '4.5/5',
                        'text' => 'Great service installing our new water heater. Highly recommended.',
                        'author' => 'Mark L.',
                    ],
                ],
            ],
            [
                'type' => 'faq',
                'heading' => 'Frequently Asked Questions',
                'items' => [
                    [
                        'question' => 'Do you offer 24/7 emergency services?',
                        'answer' => 'Yes, our team is on call day and night for any plumbing emergencies.',
                    ],
                    [
                        'question' => 'Are your plumbers licensed and insured?',
                        'answer' => 'Absolutely. All our staff are fully licensed, bonded, and insured.',
                    ],
                    [
                        'question' => 'Do you provide free estimates?',
                        'answer' => 'Yes, we offer free, no-obligation estimates for most major projects.',
                    ],
                ],
            ],
            [
                'type' => 'contact',
                'heading' => 'Get In Touch',
                'phone' => '555-0198',
                'email' => 'service@example.com',
                'address' => '123 Fake Street, Springfield',
            ],
            [
                'type' => 'booking_button',
                'label' => 'Book a plumber',
                'url' => 'tel:5550198',
            ],
        ];

        $html = app(SiteBlockRenderer::class)->render($blocks, ['tokens' => $tokens] + self::CONTEXT);
        $fullHtml = '<!doctype html><html><head><meta charset="utf-8"><base target="_blank"></head><body>'.$html.'</body></html>';

        $path = '/home/goaiez/agents/grs-antig-site/.agents/supervisor/SAMPLE-PAGE.html';
        file_put_contents($path, $fullHtml);

        $this->assertFileExists($path);
        $this->assertGreaterThan(4000, filesize($path));
        $this->assertStringContainsString('Calder', $fullHtml);
        $this->assertStringContainsString('site-block__inner', $fullHtml);
        $this->assertEquals(2, substr_count($fullHtml, 'Book a plumber'));
        $this->assertStringContainsString('site-cta--primary', $fullHtml);
    }
}
