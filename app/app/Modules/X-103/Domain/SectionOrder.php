<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

final class SectionOrder
{
    /** Stable: listed types in the order given, unlisted types after them in their current order. */
    public static function apply(array $blocks, array $order): array
    {
        $rank = array_flip(array_values($order));
        $keyed = [];
        foreach ($blocks as $i => $block) {
            $type = is_array($block) ? (string) ($block['type'] ?? '') : '';
            $keyed[] = [$rank[$type] ?? PHP_INT_MAX, $i, $block];
        }
        usort($keyed, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_values(array_map(fn ($k) => $k[2], $keyed));
    }
}
