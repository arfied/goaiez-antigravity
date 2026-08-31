<?php

declare(strict_types=1);

namespace App\Livewire\Advanced;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.account')]
class RankTracker extends Component
{
    public string $selectedKeyword = 'best local service near me';

    public int $gridSize = 3; // 3x3 or 5x5

    public int $radiusMiles = 5;

    public ?string $scanNotification = null;

    /** @var array<int, string> */
    public array $keywords = [
        'best local service near me',
        'emergency contractor repair',
        'top rated clinic reviews',
        'licensed specialist consultation',
        'same day service provider',
    ];

    public function runScan(): void
    {
        $this->scanNotification = 'Geo-grid scan complete for "'.$this->selectedKeyword.'" across '.$this->radiusMiles.' miles radius. All 9 node points updated.';
    }

    public function render(): View
    {
        $gridData = [
            ['lat' => '+1.5mi N', 'lng' => '-1.5mi W', 'rank' => 1, 'trend' => 'up', 'competitors_ahead' => 0],
            ['lat' => '+1.5mi N', 'lng' => '0.0mi Center', 'rank' => 1, 'trend' => 'same', 'competitors_ahead' => 0],
            ['lat' => '+1.5mi N', 'lng' => '+1.5mi E', 'rank' => 2, 'trend' => 'up', 'competitors_ahead' => 1],
            ['lat' => '0.0mi Center', 'lng' => '-1.5mi W', 'rank' => 1, 'trend' => 'up', 'competitors_ahead' => 0],
            ['lat' => '0.0mi Center', 'lng' => '0.0mi Center', 'rank' => 1, 'trend' => 'same', 'competitors_ahead' => 0],
            ['lat' => '0.0mi Center', 'lng' => '+1.5mi E', 'rank' => 3, 'trend' => 'up', 'competitors_ahead' => 2],
            ['lat' => '-1.5mi S', 'lng' => '-1.5mi W', 'rank' => 2, 'trend' => 'up', 'competitors_ahead' => 1],
            ['lat' => '-1.5mi S', 'lng' => '0.0mi Center', 'rank' => 1, 'trend' => 'same', 'competitors_ahead' => 0],
            ['lat' => '-1.5mi S', 'lng' => '+1.5mi E', 'rank' => 4, 'trend' => 'down', 'competitors_ahead' => 3],
        ];

        $avgRank = 1.6;
        $top3Dominance = 89; // 8 of 9 in top 3

        return view('livewire.advanced.rank-tracker', [
            'gridData' => $gridData,
            'avgRank' => $avgRank,
            'top3Dominance' => $top3Dominance,
        ]);
    }
}
