<?php

declare(strict_types=1);

namespace App\Modules\X154\Ui;

use App\Modules\X154\Actions\LexiconApplyAction;
use App\Modules\X154\Actions\LexiconReadbackAction;
use App\Modules\X154\Models\TenantLexicon;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ReadbackScreen extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $genericTerm = '';

    public string $preferredTerm = '';

    public string $category = 'service_name';

    public string $templateText = '';

    public ?string $preview = null;

    public ?string $success = null;

    public ?string $error = null;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function setMapping(LexiconReadbackAction $action): void
    {
        $this->reset('success', 'error');

        if (trim($this->genericTerm) === '' || trim($this->preferredTerm) === '' || trim($this->category) === '') {
            $this->error = 'Please provide all terms to map.';

            return;
        }

        $lexicon = $action->setMapping(
            Tenancy::idOrFail(),
            $this->genericTerm,
            $this->preferredTerm,
            $this->category
        );

        $this->success = 'Recorded vocabulary mapping: '.$lexicon->generic_term.' to '.$lexicon->preferred_term.'. An existing entry for this generic term was updated if it existed. Nothing downstream is wired yet.';
        $this->reset('genericTerm', 'preferredTerm', 'category');
    }

    public function previewReadback(LexiconApplyAction $action): void
    {
        $this->reset('error');

        if (trim($this->templateText) === '') {
            $this->error = 'Please provide text to preview.';

            return;
        }

        $this->preview = $action->applyLexicon(Tenancy::idOrFail(), $this->templateText);
    }

    public function render()
    {
        $lexicons = ($this->businessId > 0)
            ? TenantLexicon::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-154::readback-screen', [
            'lexicons' => $lexicons,
        ]);
    }
}
