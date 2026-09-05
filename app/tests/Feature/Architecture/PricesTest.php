<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

final class PricesTest extends TestCase
{
    public function test_disclaimer_on_assistant_briefs_is_only_accessed_by_price_book(): void
    {
        $finder = new Finder;
        $finder->files()
            ->in(app_path())
            ->name('*.php')
            ->notName('PriceBook.php')
            ->notName('AssistantBrief.php')
            ->notName('DefaultsManifest.php');

        $offenders = [];
        foreach ($finder as $file) {
            $tokens = token_get_all($file->getContents());
            foreach ($tokens as $t) {
                if (is_array($t)) {
                    $name = token_name($t[0]);
                    $val = strtolower($t[1]);
                    if ($name === 'T_COMMENT' || $name === 'T_DOC_COMMENT') {
                        continue;
                    }
                    if (str_contains($val, 'quote_disclaimer')) {
                        $offenders[] = $file->getRelativePathname();
                        break;
                    }
                }
            }
        }

        $this->assertEmpty($offenders, 'quote_disclaimer is accessed outside of PriceBook.php: '.implode(', ', $offenders));
    }

    public function test_urgent_terms_store_is_only_accessed_by_urgent_terms_service(): void
    {
        $finder = new Finder;
        $finder->files()
            ->in(app_path())
            ->name('*.php')
            ->notName('UrgentTerms.php')
            ->notName('UrgentTerm.php')
            ->notName('AssistantBriefPolicy.php');

        $offenders = [];
        foreach ($finder as $file) {
            $tokens = token_get_all($file->getContents());
            foreach ($tokens as $t) {
                if (is_array($t)) {
                    $name = token_name($t[0]);
                    $val = $t[1];
                    if ($name === 'T_COMMENT' || $name === 'T_DOC_COMMENT') {
                        continue;
                    }

                    if (($name === 'T_STRING' && $val === 'UrgentTerm') || ($name === 'T_NAME_QUALIFIED' && str_ends_with($val, '\UrgentTerm'))) {
                        $offenders[] = $file->getRelativePathname();
                        break;
                    }

                    if ($name === 'T_CONSTANT_ENCAPSED_STRING' && str_contains(strtolower($val), 'urgent_terms')) {
                        // AgentGroundingSource has it as an enum value, not accessing the store.
                        if (! str_contains($file->getFilename(), 'AgentGroundingSource')) {
                            $offenders[] = $file->getRelativePathname();
                            break;
                        }
                    }
                }
            }
        }

        $this->assertEmpty($offenders, 'Urgent terms store accessed outside of UrgentTerms.php: '.implode(', ', array_unique($offenders)));
    }

    public function test_no_second_store_for_emergency_keywords(): void
    {
        $finder = new Finder;
        $finder->files()
            ->in(app_path())
            ->in(database_path('migrations'))
            ->name('*.php');

        $offenders = [];
        foreach ($finder as $file) {
            $tokens = token_get_all($file->getContents());
            foreach ($tokens as $t) {
                if (is_array($t)) {
                    $name = token_name($t[0]);
                    $val = strtolower($t[1]);
                    if ($name === 'T_COMMENT' || $name === 'T_DOC_COMMENT') {
                        continue;
                    }
                    if (str_contains($val, 'emergency_keywords')) {
                        $filename = $file->getFilename();
                        if (! str_contains($filename, 'drop_emergency_keywords') && ! str_contains($filename, 'create_support_settings')) {
                            $offenders[] = $file->getRelativePathname();
                            break;
                        }
                    }
                }
            }
        }

        $this->assertEmpty($offenders, 'emergency_keywords column/store found outside of legacy migrations: '.implode(', ', array_unique($offenders)));
    }

    public function test_no_seeder_or_factory_seeds_a_price_urgent_word_or_emergency_keyword(): void
    {
        $finder = new Finder;
        $finder->files()
            ->in(database_path('seeders'))
            ->in(database_path('factories'))
            ->name('*.php');

        $offenders = [];
        foreach ($finder as $file) {
            $tokens = token_get_all($file->getContents());
            foreach ($tokens as $t) {
                if (is_array($t)) {
                    $name = token_name($t[0]);
                    $val = strtolower($t[1]);
                    if ($name === 'T_COMMENT' || $name === 'T_DOC_COMMENT') {
                        continue;
                    }
                    if (str_contains($val, 'pricebookitem') || str_contains($val, 'urgentterm') || str_contains($val, 'emergency_keyword')) {
                        $offenders[] = $file->getRelativePathname();
                        break;
                    }
                }
            }
        }

        $this->assertEmpty($offenders, 'Seeder or factory seeds a price, urgent word, or emergency keyword: '.implode(', ', array_unique($offenders)));
    }

    public function test_emergency_line_on_assistant_briefs_is_only_accessed_by_urgent_terms(): void
    {
        $finder = new Finder;
        $finder->files()
            ->in(app_path())
            ->name('*.php')
            ->notName('UrgentTerms.php')
            ->notName('AssistantBrief.php');

        $offenders = [];
        foreach ($finder as $file) {
            $tokens = token_get_all($file->getContents());
            foreach ($tokens as $t) {
                if (is_array($t)) {
                    $name = token_name($t[0]);
                    $val = strtolower($t[1]);
                    if ($name === 'T_COMMENT' || $name === 'T_DOC_COMMENT') {
                        continue;
                    }
                    if (str_contains($val, 'emergency_line')) {
                        $offenders[] = $file->getRelativePathname();
                        break;
                    }
                }
            }
        }

        $this->assertEmpty($offenders, 'emergency_line is accessed outside of UrgentTerms.php: '.implode(', ', $offenders));
    }

    public function test_assistant_toggles_are_only_accessed_by_assistant_toggles_service(): void
    {
        $finder = new Finder;
        $finder->files()
            ->in(app_path())
            ->name('*.php')
            ->notName('AssistantToggles.php')
            ->notName('AssistantBrief.php')
            ->notName('AssistantToggle.php');

        $offenders = [];
        foreach ($finder as $file) {
            $tokens = token_get_all($file->getContents());
            foreach ($tokens as $t) {
                if (is_array($t)) {
                    $name = token_name($t[0]);
                    $val = strtolower($t[1]);
                    if ($name === 'T_COMMENT' || $name === 'T_DOC_COMMENT') {
                        continue;
                    }
                    if (str_contains($val, 'quotes_enabled') || str_contains($val, 'review_ask_enabled') || str_contains($val, 'nudge_enabled')) {
                        $offenders[] = $file->getRelativePathname();
                        break;
                    }
                }
            }
        }

        $this->assertEmpty($offenders, '*_enabled toggles accessed outside of AssistantToggles.php: '.implode(', ', $offenders));
    }

    public function test_assistant_brief_is_only_accessed_through_its_three_owners(): void
    {
        $finder = new Finder;
        $finder->files()
            ->in(app_path())
            ->name('*.php')
            ->notName('PriceBook.php')
            ->notName('UrgentTerms.php')
            ->notName('AssistantToggles.php')
            ->notName('AssistantBrief.php')
            ->notName('AssistantBriefPolicy.php')
            ->notName('AssistantAnswers.php');

        $offenders = [];
        foreach ($finder as $file) {
            $tokens = token_get_all($file->getContents());
            foreach ($tokens as $t) {
                if (is_array($t)) {
                    $name = token_name($t[0]);
                    $val = $t[1];
                    if ($name === 'T_COMMENT' || $name === 'T_DOC_COMMENT') {
                        continue;
                    }
                    if (($name === 'T_STRING' && $val === 'AssistantBrief') || ($name === 'T_NAME_QUALIFIED' && str_ends_with($val, '\AssistantBrief'))) {
                        $offenders[] = $file->getRelativePathname();
                        break;
                    }
                }
            }
        }

        $this->assertEmpty($offenders, 'AssistantBrief accessed outside of its three owners: '.implode(', ', $offenders));
    }
}