<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\X103\Domain\SiteTemplateRenderer;
use App\Modules\X103\Domain\SiteTemplates;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('webstudio:render-templates {--out= : directory to write into}')]
#[Description('Render every site template with sample content to standalone HTML for the Webstudio import')]
final class WebstudioRenderTemplates extends Command
{
    public const array SAMPLE_BLOCKS = [
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
        [
            'type' => 'stats',
            'heading' => 'By the numbers',
            'items' => [
                ['value' => '20+', 'label' => 'Years in business'],
                ['value' => '4,800', 'label' => 'Jobs completed'],
                ['value' => '4.9', 'label' => 'Average rating'],
            ],
        ],
        [
            'type' => 'cta_band',
            'heading' => 'Ready when you are',
            'text' => 'Same-day appointments most days.',
            'label' => 'Get a quote',
            'url' => '#form',
        ],
        [
            'type' => 'form',
            'heading' => 'Request a callback',
            'definition_id' => 'sample',
            'fields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
            ],
        ],
        [
            'type' => 'products',
            'heading' => 'Popular items',
            'items' => [
                ['name' => 'Starter kit', 'price_text' => '$49', 'description' => 'Everything a first visit needs.'],
                ['name' => 'Gift card', 'price_text' => 'From $25', 'description' => 'Any amount, never expires.'],
            ],
        ],
    ];

    public function handle(): int
    {
        $out = $this->option('out');
        if (! is_string($out) || $out === '') {
            $out = storage_path('app/private/webstudio/templates');
        }

        if (! is_dir($out)) {
            mkdir($out, 0755, true);
        }

        $renderer = app(SiteTemplateRenderer::class);
        $manifest = [];
        $hasError = false;

        foreach (array_keys(SiteTemplates::TEMPLATES) as $id) {
            $template = SiteTemplates::get($id);
            $context = [
                'businessName' => 'Calder & Sons Plumbing',
                'tenant_storage_url_prefix' => '/m/',
                'form_action_base' => '',
                'tokens' => [
                    'palette' => $template['palette'],
                    'type_pairing' => $template['type_pairing'],
                ],
            ];

            try {
                $htmlFragment = $renderer->render($template, self::SAMPLE_BLOCKS, $context);

                $html = "<!doctype html>\n"
                    ."<html lang=\"en\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><title>{$template['label']}</title></head><body>\n"
                    ."{$htmlFragment}\n"
                    ."</body></html>\n";

                $dir = $out.'/'.$id;
                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                $bytes = file_put_contents($dir.'/index.html', $html);
                if ($bytes === false) {
                    throw new \RuntimeException("Failed to write $dir/index.html");
                }

                $manifest[$id] = [
                    'label' => $template['label'],
                    'for' => $template['for'],
                    'bytes' => $bytes,
                ];

                $this->line("{$id}  {$template['label']}  {$bytes} bytes");
            } catch (\Throwable $e) {
                $this->error("Failed to render {$id}: ".$e->getMessage());
                $hasError = true;
            }
        }

        file_put_contents(
            $out.'/templates.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );

        return $hasError ? 1 : 0;
    }
}
