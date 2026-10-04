<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

use App\Services\Industry\SiteStyle;
use Illuminate\Support\Facades\View;

/**
 * Draws a page through a site template (SiteTemplates). The template gets the first block of each section type it lists,
 * plus small helpers, and owns every tag on the page; its stylesheet replaces the shared one (SiteBlockRenderer's), so the
 * colours still come only from the --color-* variables written here and an owner's colour edits restyle it.
 */
final class SiteTemplateRenderer
{
    /**
     * @param  array<string, mixed>  $template  SiteTemplates::get()
     * @param  array<int, mixed>  $contentBlocks
     * @param  array<string, mixed>  $context
     */
    public function render(array $template, array $contentBlocks, array $context): string
    {
        $p = $context['tokens']['palette'] ?? [];
        $t = $context['tokens']['type_pairing'] ?? [];
        $tp = $template['palette'];
        $surface = (string) ($p['surface'] ?? $tp['surface']);
        $card = (string) ($p['card'] ?? $tp['card']);
        $ink = (string) ($p['ink'] ?? $tp['ink']);
        $primary = (string) ($p['primary'] ?? $tp['primary']);
        $accent = (string) ($p['accent'] ?? $tp['accent']);
        $type = [
            'heading' => (string) ($t['heading'] ?? $template['type_pairing']['heading']),
            'body' => (string) ($t['body'] ?? $template['type_pairing']['body']),
        ];

        $vars = ':root {'."\n"
            .'    --color-surface: '.e($surface).";\n"
            .'    --color-canvas: '.e($surface).";\n"
            .'    --color-paper: '.e($surface).";\n"
            .'    --color-card: '.e($card).";\n"
            .'    --color-ink: '.e($ink).";\n"
            .'    --color-primary: '.e($primary).";\n"
            .'    --color-accent: '.e($accent).";\n"
            .'    --color-accent-text: '.e(SiteStyle::readable($accent, [$surface, $card], $ink)).";\n"
            .'    --color-on-primary: '.e(SiteStyle::textOn($primary, [$surface, $ink, '#ffffff', '#111111'])).";\n"
            .'    --font-heading: '.e($type['heading']).";\n"
            .'    --font-body: '.e($type['body']).";\n"
            .'}';

        $blocks = [];
        $index = [];
        foreach ($contentBlocks as $i => $block) {
            $blockType = is_array($block) ? ($block['type'] ?? null) : null;
            if (is_string($blockType) && in_array($blockType, $template['sections'], true) && ! isset($blocks[$blockType])) {
                $blocks[$blockType] = $block;
                $index[$blockType] = $i;
            }
        }

        $txt = static fn (mixed $v): ?string => is_scalar($v) && trim((string) $v) !== '' ? trim((string) $v) : null;
        $editable = ! empty($context['editable']);
        $prefix = (string) ($context['tenant_storage_url_prefix'] ?? '');
        $contact = is_array($blocks['contact'] ?? null) ? $blocks['contact'] : [];
        $phone = $txt($contact['phone'] ?? null);

        $html = '<style>'."\n".SiteFonts::faceCss($type).$vars."\n".SiteTemplates::css($template['id']).'</style>'."\n";
        $html .= View::make(SiteTemplates::view($template['id']), [
            'b' => $blocks,
            // Marks a section for the Studio: the canvas finds the block a click landed in by these two attributes.
            'at' => static fn (string $blockType): string => isset($index[$blockType])
                ? ' data-block-index="'.(int) $index[$blockType].'" data-block-type="'.e($blockType).'"' : '',
            'f' => static fn (string $field): string => $editable ? ' data-field="'.e($field).'"' : '',
            'img' => static fn (mixed $path): ?string => $txt($path) === null ? null : $prefix.basename((string) $path),
            'txt' => $txt,
            // A button link: the safe schemes every section allows, or a jump to a section of this page.
            'link' => static fn (mixed $v): ?string => ($u = $txt($v)) !== null
                && (BlockPatchSchema::isSafeLink($u) || preg_match('/^#[A-Za-z][A-Za-z0-9_-]*$/', $u) === 1) ? $u : null,
            'name' => trim((string) ($context['businessName'] ?? '')),
            'phone' => $phone,
            'tel' => $phone === null ? null : 'tel:'.preg_replace('/[^0-9+]/', '', $phone),
            'email' => $txt($contact['email'] ?? null),
            'address' => $txt($contact['address'] ?? null),
            'hours' => is_array($contact['hours'] ?? null) ? $contact['hours'] : [],
            'facts' => is_array($contact['facts'] ?? null) ? array_filter($contact['facts'], static fn (mixed $v): bool => $txt($v) !== null) : [],
        ])->render();

        return $html;
    }
}
