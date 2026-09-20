<?php

declare(strict_types=1);

namespace App\Modules\X160\Ui;

use App\Modules\X160\Models\Document;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Your documents'])]
class UploadDrop extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $title = '';
    public string $content = '';
    public ?string $success = null;
    public ?string $error = null;

    public function mount(): void
    {
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function createDocument(): void
    {
        $this->success = null;
        $this->error = null;

        if (empty($this->title) || empty($this->content)) {
            $this->error = 'Title and content are required.';
            return;
        }

        $result = app(\App\Modules\X160\Domain\DocumentExtractionEngine::class)->ingest(
            Tenancy::idOrFail(),
            $this->title,
            $this->content
        );

        $this->success = 'Recorded document ' . $result['document_id'] . '. This feeds the review lists; nothing downstream is wired to it yet.';
        $this->title = '';
        $this->content = '';
    }

    public function render()
    {
        $docs = ($this->businessId > 0)
            ? Document::where('business_id', $this->businessId)->orderByDesc('id')->get()
            : collect();

        return view('x-160::upload-drop', [
            'documents' => $docs,
        ]);
    }
}
