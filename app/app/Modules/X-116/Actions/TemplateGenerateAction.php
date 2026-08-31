<?php

declare(strict_types=1);

namespace App\Modules\X116\Actions;

use App\Modules\X116\Events\TemplateGenerated;
use App\Modules\X116\Models\Template;
use App\Modules\X116\Models\TemplateBlock;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class TemplateGenerateAction
{
    /**
     * Generates an industry-specific template.
     * 1. Every block on a generated page names the module and manifest it rendered from; block with no source fails (TEST ANCHOR).
     * 2. Different industry families produce visibly different palette, type scale, rhythm (TEST ANCHOR & G6-29).
     */
    public function generateTemplate(
        int $businessId,
        string $industryCode,
        string $funnelType = 'quote',
        array $blocksDefinition = []
    ): Template {
        // Compute distinct design tokens based on industry family (TEST ANCHOR & G6-29)
        $tokensByIndustry = [
            'plumbing' => [
                'palette' => ['primary' => '#0055AA', 'accent' => '#FF6600', 'bg' => '#F4F7FB'],
                'type_scale' => '1.250-major-third',
                'rhythm' => 'compact-high-density',
            ],
            'legal' => [
                'palette' => ['primary' => '#1A2B49', 'accent' => '#C5A059', 'bg' => '#FCFBF9'],
                'type_scale' => '1.333-perfect-fourth',
                'rhythm' => 'generous-editorial',
            ],
        ];

        $designTokens = $tokensByIndustry[$industryCode] ?? [
            'palette' => ['primary' => '#2D3748', 'accent' => '#3182CE', 'bg' => '#FFFFFF'],
            'type_scale' => '1.200-minor-third',
            'rhythm' => 'standard-balanced',
        ];

        $template = Template::create([
            'business_id' => $businessId,
            'industry_code' => $industryCode,
            'funnel_type' => $funnelType,
            'design_tokens' => $designTokens,
            'conversion_score' => 0.00,
        ]);

        // Default blocks if none passed
        if (empty($blocksDefinition)) {
            $blocksDefinition = [
                ['module' => 'X-114', 'block' => 'brand_kit', 'order' => 1],
                ['module' => 'X-103', 'block' => 'booking_calendar', 'order' => 2],
            ];
        }

        foreach ($blocksDefinition as $block) {
            // TEST ANCHOR: A block with no @renders source fails generation
            if (empty($block['module']) || empty($block['block'])) {
                throw new InvalidArgumentException('Block generation failed: every block must name its source module and @renders manifest block (TEST ANCHOR)');
            }

            TemplateBlock::create([
                'business_id' => $businessId,
                'template_id' => $template->id,
                'render_source_module' => $block['module'],
                'render_source_block' => $block['block'],
                'order_index' => (int) ($block['order'] ?? 1),
                'config' => $block['config'] ?? null,
            ]);
        }

        Event::dispatch(new TemplateGenerated($businessId, $template->id, $industryCode, $funnelType));

        return $template;
    }
}
