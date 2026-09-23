<?php

declare(strict_types=1);

namespace App\Modules\X212\Ui;

use App\Modules\X212\Actions\MigrationDryRunAction;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Import from another system'])]
class PickSource extends Component
{
    public const SOURCES = ['service_titan' => 'ServiceTitan', 'jobber' => 'Jobber', 'housecall_pro' => 'Housecall Pro', 'spreadsheet' => 'A spreadsheet export'];

    public string $sourceSystem = 'spreadsheet';

    public string $csv = '';

    public ?int $lastRunId = null;

    public function dryRun(MigrationDryRunAction $action): void
    {
        $this->validate(['sourceSystem' => ['required', 'in:'.implode(',', array_keys(self::SOURCES))], 'csv' => ['required', 'string', 'max:200000']]);
        $records = $this->parse($this->csv);
        if ($records === []) {
            $this->addError('csv', 'The first line must be a header row and at least one record must follow it.');

            return;
        }
        $run = $action->handle((int) Tenancy::idOrFail(), $this->sourceSystem, $records);
        $this->lastRunId = $run->id;
        $this->csv = '';
        session()->flash('status', 'Dry run '.$run->id.' finished: '.$run->imported_records.' records can be imported, '.$run->rejected_records.' rejected. Nothing has been imported.');
    }

    /** @return array<int, array<string, string>> */
    private function parse(string $csv): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $csv)), fn ($l) => $l !== ''));
        if (count($lines) < 2) {
            return [];
        }
        $headers = array_map(fn ($h) => strtolower(trim($h)), str_getcsv(array_shift($lines)));
        $records = [];
        foreach ($lines as $line) {
            $cells = str_getcsv($line);
            $records[] = array_combine($headers, array_pad(array_slice($cells, 0, count($headers)), count($headers), ''));
        }

        return $records;
    }

    public function render()
    {
        return view('x-212::pick-source');
    }
}
