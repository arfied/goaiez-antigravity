<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Modules\X103\Models\Page;
use App\Support\Readability;

/**
 * What a screen reader or a plain reader would trip on in the drafted pages.
 *
 * Deterministic, no AI, no network. It reads `pages.draft_blocks` — never the
 * rendered HTML, because SiteBlockRenderer drops an invalid block before any
 * HTML exists. A row is ['key' => …, 'page' => slug, 'label' => …,
 * 'hint' => …, 'route' => route name or null, 'here' => bool], one per page
 * per problem. An empty list means every drafted page passed every check.
 *
 * @return list<array{key: string, page: string, label: string, hint: string, route: ?string, here: bool}>
 */
final class SiteReadabilityAction
{
    public const MAX_READING_GRADE = 8;

    public function handle(int $businessId): array
    {
        $rows = [];
        foreach (Page::where('business_id', $businessId)->orderBy('id')->get() as $page) {
            $blocks = is_array($page->draft_blocks) ? $page->draft_blocks : [];
            $slug = (string) $page->slug;
            $undescribed = 0;
            $unlabelled = 0;
            $hasH1 = false;
            $text = [];
            foreach ($blocks as $block) {
                if (! is_array($block)) {
                    continue;
                }
                $type = $block['type'] ?? null;
                if ($type === 'hero') {
                    if ($this->present($block, 'headline')) {
                        $hasH1 = true;
                    }
                    if ($this->present($block, 'image_path') && ! $this->present($block, 'image_alt')) {
                        $undescribed++;
                    }
                    if ($this->present($block, 'subline')) {
                        $text[] = (string) $block['subline'];
                    }
                }
                if ($type === 'gallery') {
                    foreach ((array) ($block['items'] ?? []) as $item) {
                        if (is_array($item) && $this->present($item, 'image_path') && ! $this->present($item, 'alt')) {
                            $undescribed++;
                        }
                    }
                }
                if ($type === 'form') {
                    foreach ((array) ($block['fields'] ?? []) as $field) {
                        if (is_array($field) && ! $this->present($field, 'label')) {
                            $unlabelled++;
                        }
                    }
                }
                if ($type === 'about' && $this->present($block, 'text')) {
                    $text[] = (string) $block['text'];
                }
                if ($type === 'services') {
                    foreach ((array) ($block['items'] ?? []) as $item) {
                        if (is_array($item) && $this->present($item, 'description')) {
                            $text[] = (string) $item['description'];
                        }
                    }
                }
            }
            if ($undescribed > 0) {
                $rows[] = ['key' => 'picture_description', 'page' => $slug, 'label' => $undescribed.' '.($undescribed === 1 ? 'picture has' : 'pictures have').' no description', 'hint' => 'A screen reader says the description instead of the picture. Type one per stored picture above, then draft again.', 'route' => null, 'here' => true];
            }
            if ($unlabelled > 0) {
                $rows[] = ['key' => 'form_label', 'page' => $slug, 'label' => $unlabelled.' form '.($unlabelled === 1 ? 'field has' : 'fields have').' no label', 'hint' => 'A field with no label cannot be filled in by voice or screen reader. Name every field on the form.', 'route' => 'x-155.forms', 'here' => false];
            }
            if (! $hasH1) {
                $rows[] = ['key' => 'no_heading', 'page' => $slug, 'label' => 'No main heading', 'hint' => 'The page has no headline block, so a reader has nothing to skip to. Add a hero section on Pages.', 'route' => 'x-103.pages', 'here' => false];
            }
            $grade = Readability::gradeLevel(implode(' ', $text));
            if ($grade !== null && $grade > self::MAX_READING_GRADE) {
                $rows[] = ['key' => 'reading_grade', 'page' => $slug, 'label' => 'Reads at grade '.$grade, 'hint' => 'Shorter sentences and plainer words bring it under grade '.self::MAX_READING_GRADE.'. Ask the AI on Pages to simplify it.', 'route' => 'x-103.pages', 'here' => false];
            }
        }

        return $rows;
    }

    private function present(array $a, string $key): bool
    {
        return isset($a[$key]) && is_scalar($a[$key]) && trim((string) $a[$key]) !== '';
    }
}
