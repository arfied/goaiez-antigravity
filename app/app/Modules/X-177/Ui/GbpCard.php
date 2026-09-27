<?php

declare(strict_types=1);

namespace App\Modules\X177\Ui;

use App\Modules\X177\Actions\GbpPostAction;
use App\Modules\X177\Actions\GbpStateAction;
use App\Modules\X177\Models\GbpConnection;
use App\Modules\X177\Models\GbpPost;
use App\Modules\X177\Models\GbpStateLog;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Google profile'])]
class GbpCard extends Component
{
    #[Locked]
    public int $businessId = 0;

    public bool $isSample = false;

    public ?int $viewingLogId = null;

    public function mount(): void
    {
        if ($this->businessId === 0) {
            $tenantId = Tenancy::id() ?: 0;
            if ($tenantId <= 0) {
                abort(403, 'Tenant context is required');
            }
            $this->businessId = (int) $tenantId;
        }
        Tenancy::set($this->businessId);
    }

    public function toggleSample(): void
    {
        $this->isSample = ! $this->isSample;
        $this->viewingLogId = null;
    }

    public function toggleLog(int $connectionId): void
    {
        if ($this->viewingLogId === $connectionId) {
            $this->viewingLogId = null;
        } else {
            $this->viewingLogId = $connectionId;
        }
    }

    public function pollState(int $connectionId): void
    {
        if ($this->isSample) {
            return;
        }
        Tenancy::set($this->businessId);

        $action = app(GbpStateAction::class);
        $action->pollState($this->businessId, $connectionId);
    }

    public array $postContent = [];

    public array $postImage = [];

    public function postUpdate(int $connectionId): void
    {
        if ($this->isSample) {
            return;
        }

        Tenancy::set($this->businessId);

        $content = $this->postContent[$connectionId] ?? '';
        $imageUrl = $this->postImage[$connectionId] ?? null;
        if (trim($content) === '') {
            return;
        }

        $action = app(GbpPostAction::class);
        $result = $action->post($this->businessId, $connectionId, $content, 'update', empty(trim($imageUrl ?? '')) ? null : trim($imageUrl));

        $resultStatus = $result['status'] ?? 'failed';
        $toast = $result['message'] ?? 'Failed to post.';
        if ($resultStatus === 'posted') {
            $toast = 'Posted to your Google profile. It can take up to 48 hours to show on Google.';
        } elseif ($resultStatus === 'publishing') {
            $toast = 'Sent to Google through Zernio — it is still publishing.';
        } elseif ($resultStatus === 'refused_image') {
            $toast = $result['message'];
        }

        $this->dispatch('toast', ['message' => $toast, 'type' => ($resultStatus === 'posted' || $resultStatus === 'publishing') ? 'success' : 'error']);

        $this->postContent[$connectionId] = '';
        $this->postImage[$connectionId] = '';
    }

    public function render()
    {
        Tenancy::set($this->businessId);

        $connections = collect();
        if (! $this->isSample) {
            $connections = GbpConnection::where('business_id', $this->businessId)->get()->map(function ($c) {
                $c->latest_post = GbpPost::where('business_id', $this->businessId)
                    ->where('connection_id', $c->id)
                    ->latest('created_at')
                    ->first();

                $c->latest_log = GbpStateLog::where('business_id', $this->businessId)
                    ->where('connection_id', $c->id)
                    ->latest('created_at')
                    ->first();

                $c->plain_status = $c->profile_status === 'suspended' ? 'Profile is suspended' : 'We are not reading your profile’s status yet';

                if ($c->latest_post) {
                    $c->post_status_text = $c->latest_post->status;
                    if ($c->latest_post->status === 'posted') {
                        $c->post_status_text = 'Posted';
                    } elseif ($c->latest_post->status === 'publishing') {
                        $c->post_status_text = 'Still publishing';
                    } elseif ($c->latest_post->status === 'not_connected') {
                        $c->post_status_text = 'Not sent — connect your profile';
                    } elseif ($c->latest_post->status === 'failed') {
                        $c->post_status_text = 'Google refused it: '.($c->latest_post->failure_reason ?? 'unknown');
                    }
                }

                return $c;
            });
        } else {
            $connections = collect([
                (object) [
                    'id' => 999,
                    'external_label' => 'Sample Location',
                    'profile_status' => 'suspended',
                    'plain_status' => 'Profile is suspended',
                    'latest_post' => (object) ['content' => 'Summer sale started today', 'status' => 'posted'],
                    'post_status_text' => 'Posted',
                    'latest_log' => (object) ['event_type' => 'state_read', 'details' => ['old' => 'active', 'new' => 'suspended'], 'created_at' => now()],
                ],
            ]);
        }

        $isEmpty = ! $this->isSample && $connections->isEmpty();

        return view('x-177::gbp-card', [
            'connections' => $connections,
            'isEmpty' => $isEmpty,
        ]);
    }
}
