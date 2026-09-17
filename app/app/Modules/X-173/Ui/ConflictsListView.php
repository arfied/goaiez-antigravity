<?php

declare(strict_types=1);

namespace App\Modules\X173\Ui;

use App\Modules\X173\Actions\ConflictResolveAction;
use App\Modules\X173\Models\AccountingSyncConflict;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Sync conflicts'])]
class ConflictsListView extends Component
{
    public array $resolutions = [];

    public ?string $error = null;

    public ?string $success = null;

    public function resolve(int $conflictId, ConflictResolveAction $action)
    {
        $this->error = null;
        $this->success = null;

        $answer = $this->resolutions[$conflictId] ?? '';

        try {
            $r = $action->handle(Tenancy::idOrFail(), $conflictId, $answer);

            if ($r['status'] === 'refused') {
                $this->error = $r['message'];
            } elseif ($r['status'] === 'resolved') {
                $this->success = $r['message'];
                unset($this->resolutions[$conflictId]);
            }
        } catch (ModelNotFoundException $e) {
            $this->error = "That conflict isn't in this account.";
        } catch (\Throwable $e) {
            $this->error = 'We could not record that account: '.$e->getMessage();
        }
    }

    public function render()
    {
        abort_unless(Tenancy::check(), 403);
        $businessId = Tenancy::idOrFail();

        $conflicts = AccountingSyncConflict::where('business_id', $businessId)
            ->orderByRaw("case when status = 'open' then 0 else 1 end")
            ->orderByDesc('id')
            ->get();

        return view('x-173::conflicts-list', [
            'conflicts' => $conflicts,
        ]);
    }
}
