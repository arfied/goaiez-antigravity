<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Livewire\Admin\Concerns\AdminTable;
use App\Models\AutomationRun;
use App\Support\Admin\BulkAction;
use App\Support\Admin\Column;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * A minimal component composing the admin shell, for testing the trait itself.
 *
 * Deliberately not a subclass of a real screen: those are final, and testing
 * the shell through one would conflate "the trait works" with "that screen
 * declared its columns correctly".
 */
final class ProbeAdminTable extends Component
{
    /** @use AdminTable<AutomationRun> */
    use AdminTable;

    /** @var array<int, string>|null */
    public static ?array $captured = null;

    public bool $withDestructive = false;

    /**
     * The retry an error panel names, and the only one in the application that
     * names anything.
     *
     * ⚠️ IT EXISTS SO THE DEAD-RETRY LINT HAS A POPULATION (3009). Every real
     * `<x-ui.error-panel>` takes the component's `$refresh` default, which
     * always resolves, so a lint that only reads production call sites would
     * pass by matching nothing — 256's vacuous gate, in the test written to
     * catch a defect nobody has made yet. Renaming this method reddens that
     * lint, which is the proof the rule can fail today rather than in the
     * release that adds the first explicit retry.
     */
    public function reload(): void
    {
        //
    }

    /**
     * Named clearCaptured rather than reset: Livewire\Component::reset() already
     * exists as an instance method, and redeclaring it static is a hard fatal
     * that Pest reports as zero output and a bare exit code.
     */
    public static function clearCaptured(): void
    {
        self::$captured = null;
    }

    /**
     * @return array<int, Column>
     */
    protected function columns(): array
    {
        return [
            Column::make('automation_key', 'Automation')->sortable()->searchable(),
            Column::make('status')->sortable(),
        ];
    }

    /**
     * @return Builder<AutomationRun>
     */
    protected function query(): Builder
    {
        return AutomationRun::query();
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected function filters(): array
    {
        return ['status' => ['succeeded' => 'Succeeded', 'failed' => 'Failed']];
    }

    /**
     * @return array<int, BulkAction>
     */
    protected function bulkActions(): array
    {
        $capture = BulkAction::make('capture', 'Capture', function (Collection $records): void {
            self::$captured = $records->pluck('automation_key')->all();
        });

        return $this->withDestructive
            ? [$capture->destructive()]
            : [$capture];
    }

    public function render(): View
    {
        return view('livewire.admin.probe-table', ['rows' => $this->rows()]);
    }
}
