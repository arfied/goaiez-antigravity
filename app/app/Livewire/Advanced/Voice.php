<?php

declare(strict_types=1);

namespace App\Livewire\Advanced;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.account')]
class Voice extends Component
{
    public bool $voiceEnabled = true;

    public string $voicePersona = 'warm_professional'; // warm_professional, direct_concise, friendly_casual

    public string $greetingText = 'Thank you for calling Apex Pro Services. How can our team help you today?';

    public string $emergencyForwardNumber = '+1 (555) 987-6543';

    public ?string $saveNotification = null;

    public function saveSettings(): void
    {
        $this->saveNotification = null;
    }

    public function render(): View
    {
        $recentCalls = [
            [
                'time' => 'Today at 9:42 AM',
                'caller' => '+1 (555) 345-8901',
                'duration' => '1m 24s',
                'intent' => 'Emergency Water Leak',
                'action' => 'Forwarded to Owner Mobile',
                'status' => 'escalated',
            ],
            [
                'time' => 'Yesterday at 8:15 PM',
                'caller' => '+1 (555) 678-1234',
                'duration' => '48s',
                'intent' => 'Consultation Booking',
                'action' => 'Calendar Link Sent via SMS',
                'status' => 'resolved',
            ],
            [
                'time' => '2 days ago',
                'caller' => '+1 (555) 901-2345',
                'duration' => '35s',
                'intent' => 'Operating Hours Question',
                'action' => 'Answered via AI Voice',
                'status' => 'resolved',
            ],
        ];

        return view('livewire.advanced.voice', [
            'recentCalls' => $recentCalls,
        ]);
    }
}
