<?php

declare(strict_types=1);

namespace App\Modules\X140\Ui;

use App\Enums\UserRole;
use App\Modules\X140\Actions\ContentDraftFromConversationAction;
use App\Modules\X140\Actions\TopicIdentifyAction;
use App\Modules\X140\Models\ContentTopic;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Proposed pages'])]
class ProposedPagesView extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager, UserRole::SuperAdmin), 403);
        $this->businessId = Tenancy::id() ?? 0;
    }

    #[Locked]
    public int $businessId = 0;

    public string $topicTitle = '';

    public string $clusterKey = 'emergency_repair';

    public ?string $success = null;

    public ?string $error = null;

    public int $draftTopicId = 0;

    public string $rawContent = '';

    public function submit(TopicIdentifyAction $action): void
    {
        $this->reset(['success', 'error']);

        if (empty($this->topicTitle)) {
            $this->error = 'Topic title is required.';

            return;
        }

        $topic = $action->identify(Tenancy::idOrFail(), $this->topicTitle, $this->clusterKey ?: 'emergency_repair');
        $this->success = "Recorded proposed page '{$topic->topic_title}'. The row is created unpublished; nothing downstream is wired to it yet.";
        $this->reset(['topicTitle']);
        $this->clusterKey = 'emergency_repair';
    }

    public function draftFromConversation(ContentDraftFromConversationAction $action): void
    {
        $this->reset(['success', 'error']);

        if ($this->draftTopicId === 0 || trim($this->rawContent) === '') {
            $this->error = 'Topic and raw content are required.';

            return;
        }

        $result = $action->draftContent(
            Tenancy::idOrFail(),
            $this->draftTopicId,
            $this->rawContent
        );

        $topicTitle = ContentTopic::find($this->draftTopicId)->topic_title ?? (string) $this->draftTopicId;

        if ($result['is_published'] === false && isset($result['gate_failure_reason'])) {
            $this->success = "Recorded draft for '{$topicTitle}', but it is not published: {$result['gate_failure_reason']}";
            $this->reset(['draftTopicId', 'rawContent']);

            return;
        }

        $this->success = "Drafted content for '{$topicTitle}'. This feeds the topic lists; nothing downstream is wired to it yet.";
        $this->reset(['draftTopicId', 'rawContent']);
    }

    public function render()
    {
        $topics = ($this->businessId > 0)
            ? ContentTopic::where('business_id', $this->businessId)->with('sources')->orderByDesc('id')->get()
            : collect();

        return view('x-140::proposed-pages', [
            'topics' => $topics,
        ]);
    }
}
