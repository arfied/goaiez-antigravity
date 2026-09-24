<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\IndustryFamily;
use App\Models\IndustryStartingPoint;
use App\Support\Admin\AdminAccess;
use App\Support\Contrast;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

final class IndustryStartingPoints extends Component
{
    #[Locked]
    public string $editing = '';

    public array $palette = [
        'surface' => '',
        'ink' => '',
        'primary' => '',
        'accent' => '',
    ];

    public array $typePairing = [
        'heading' => '',
        'body' => '',
    ];

    public string $sectionOrder = '';

    private const BLOCKS = [
        'hero', 'about', 'gallery', 'services', 'reviews_strip',
        'booking_button', 'booking_form', 'faq', 'contact', 'team', 'form',
    ];

    public function mount(): void
    {
        $this->authorize(AdminAccess::GATE);
    }

    public function edit(string $family): void
    {
        $this->authorize(AdminAccess::GATE);

        $row = IndustryStartingPoint::where('family', $family)->first();

        if ($row === null) {
            Toaster::error("Unknown family: {$family}");

            return;
        }

        $this->editing = $family;
        $this->palette = $row->palette;
        $this->typePairing = $row->type_pairing;
        $this->sectionOrder = implode("\n", $row->section_order);
    }

    public function cancel(): void
    {
        $this->editing = '';
        $this->palette = ['surface' => '', 'ink' => '', 'primary' => '', 'accent' => ''];
        $this->typePairing = ['heading' => '', 'body' => ''];
        $this->sectionOrder = '';
    }

    public function save(): void
    {
        $this->authorize(AdminAccess::GATE);

        if ($this->editing === '') {
            return;
        }

        $familyEnum = IndustryFamily::tryFrom($this->editing);
        if ($familyEnum === null) {
            Toaster::error('Invalid family.');

            return;
        }

        foreach (['surface', 'ink', 'primary', 'accent'] as $color) {
            if (! Contrast::isHex($this->palette[$color] ?? '')) {
                Toaster::error('Colours are six-digit hex, like #1a2b3c.');

                return;
            }
        }

        $inkRatio = Contrast::ratio($this->palette['ink'], $this->palette['surface']);
        if ($inkRatio < Contrast::AA_TEXT) {
            Toaster::error('Text on that background reads at '.number_format($inkRatio, 2).':1 — it has to reach 4.5:1 before a site can use it.');

            return;
        }

        $accentRatio = Contrast::ratio($this->palette['accent'], $this->palette['surface']);
        if ($accentRatio < 3.0) {
            Toaster::error('The accent on that background reads at '.number_format($accentRatio, 2).':1 — 3:1 is the floor for buttons and links.');

            return;
        }

        $lines = array_values(array_filter(array_map('trim', explode("\n", $this->sectionOrder)), fn (string $line) => $line !== ''));

        foreach ($lines as $line) {
            if (! in_array($line, self::BLOCKS, true)) {
                $use = implode(', ', self::BLOCKS);
                Toaster::error("'{$line}' is not a section this site can build. Use: {$use}");

                return;
            }
        }

        if (count($lines) === 0 || $lines[0] !== 'hero') {
            Toaster::error('The home page opens with the hero.');

            return;
        }

        if (trim($this->typePairing['heading'] ?? '') === '') {
            Toaster::error('The heading font stack cannot be empty.');

            return;
        }

        if (trim($this->typePairing['body'] ?? '') === '') {
            Toaster::error('The body font stack cannot be empty.');

            return;
        }

        IndustryStartingPoint::where('family', $this->editing)->update([
            'palette' => $this->palette,
            'type_pairing' => $this->typePairing,
            'section_order' => $lines,
        ]);

        Toaster::success('Starting point saved — the next draft in this industry follows it.');
        $this->cancel();
    }

    public function render(): View
    {
        return view('livewire.admin.industry-starting-points', [
            'rows' => IndustryStartingPoint::all()->sortBy(function (IndustryStartingPoint $row) {
                // Keep the enum order if possible, though not strictly required by brief, but it's nice.
                return array_search($row->family->value, IndustryFamily::values());
            }),
        ]);
    }
}
