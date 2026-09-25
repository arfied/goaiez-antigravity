<?php
        Livewire::test(ResearchDossierPer::class, ['businessId' => $this->businessId])
            ->assertSee('Research fires only on distress')
            ->assertDontSeeHtml('wire:click="runResearch"')
            ->assertDontSeeHtml('wire:click="research"')
            ->call('toggleSample')
            ->call('select', 9901)
            ->call('toggleSample');
