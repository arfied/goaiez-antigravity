<?php

declare(strict_types=1);

namespace App\Modules\X148\Ui;

use App\Modules\X148\Actions\RetrievalIndexAction;
use App\Modules\X148\Actions\RetrievalSearchAction;
use App\Modules\X148\Models\RetrievalCache;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RetrievalLatencyEmptyrate extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $chunkTitle = '';

    public string $chunkText = '';

    public string $searchQuery = '';

    public ?string $success = null;

    public ?string $error = null;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function indexChunk(RetrievalIndexAction $action): void
    {
        $this->error = null;
        $this->success = null;
        if (empty($this->chunkTitle) || empty($this->chunkText)) {
            $this->error = 'Title and text are required.';

            return;
        }

        $action->indexChunk(Tenancy::idOrFail(), $this->chunkTitle, $this->chunkText);
        $this->success = 'Indexed knowledge chunk. This feeds the knowledge base; nothing downstream is wired to it yet.';
        $this->chunkTitle = '';
        $this->chunkText = '';
    }

    public function search(RetrievalSearchAction $action): void
    {
        $this->error = null;
        $this->success = null;
        if (empty($this->searchQuery)) {
            $this->error = 'Search query is required.';

            return;
        }

        $result = $action->search(Tenancy::idOrFail(), $this->searchQuery);
        $this->success = 'Ran retrieval search. Status: '.$result['status'].', count: '.$result['count'].'. This feeds the empty-rate lists; nothing downstream is wired to it yet.';
        $this->searchQuery = '';
    }

    public function render()
    {
        $cacheEntries = ($this->businessId > 0)
            ? RetrievalCache::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-148::retrieval-latency-emptyrate', [
            'entries' => $cacheEntries,
        ]);
    }
}
