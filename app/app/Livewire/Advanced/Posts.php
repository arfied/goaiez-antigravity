<?php

declare(strict_types=1);

namespace App\Livewire\Advanced;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.account')]
class Posts extends Component
{
    public string $postContent = '⚡ Project Completed: Finished a full commercial system inspection in local service area. Rated 5-stars by client! Book your free consultation today.';

    public string $postTopic = 'project_showcase';

    public bool $autoSchedule = true;

    public ?string $publishNotification = null;

    public function publishPost(): void
    {
        $this->publishNotification = null;
    }

    public function render(): View
    {
        $mmsNumber = '+1 (555) 304-2900';

        $recentPosts = [
            [
                'date' => 'Yesterday at 10:15 AM',
                'type' => 'Job Photo Showcase',
                'views' => 148,
                'clicks' => 19,
                'summary' => 'Before & after photo sweep of recent commercial restoration in local district.',
                'status' => 'live',
            ],
            [
                'date' => '3 days ago',
                'type' => 'Weekly Service Tip',
                'views' => 280,
                'clicks' => 34,
                'summary' => '3 essential maintenance tips for local property owners before the season change.',
                'status' => 'live',
            ],
        ];

        return view('livewire.advanced.posts', [
            'mmsNumber' => $mmsNumber,
            'recentPosts' => $recentPosts,
        ]);
    }
}
