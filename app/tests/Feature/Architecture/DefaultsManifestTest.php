<?php

namespace Tests\Feature\Architecture;

use App\Services\Config\DefaultsRegistry;
use App\Support\DefaultsManifest;

it('every setting carries a seed, a group and a description', function () {
    $missing = [];
    foreach (DefaultsManifest::settings() as $key => $entry) {
        if (! array_key_exists('seed', $entry) || ! array_key_exists('group', $entry) || ! array_key_exists('description', $entry)) {
            $missing[] = $key;
        }
    }
    expect($missing)->toBe([], 'A merged manifest entry lost a field');
});

it('every group is in the vocabulary', function () {
    $strays = [];
    foreach (DefaultsManifest::settings() as $key => $entry) {
        if (isset($entry['group']) && ! in_array($entry['group'], DefaultsManifest::GROUPS, true)) {
            $strays[] = $entry['group'];
        }
    }
    expect(array_unique($strays))->toBe([], 'These groups are not in the vocabulary');
});

it('no group is a near-duplicate of another', function () {
    $lower = array_map('strtolower', DefaultsManifest::GROUPS);
    $duplicates = array_diff_assoc($lower, array_unique($lower));
    expect($duplicates)->toBe([]);
});

it('the registry reads every declared key without throwing', function () {
    $registry = app(DefaultsRegistry::class);
    $findings = [];

    foreach (DefaultsManifest::settings() as $key => $entry) {
        if (! array_key_exists('seed', $entry)) {
            continue;
        }

        $seed = $entry['seed'];
        try {
            if (is_int($seed)) {
                $registry->int($key);
            } elseif (is_float($seed)) {
                $registry->float($key);
            } elseif (is_string($seed)) {
                $registry->string($key);
            } elseif (is_bool($seed)) {
                if (method_exists($registry, 'bool')) {
                    $registry->bool($key);
                } else {
                    $registry->value($key);
                }
            } elseif (is_array($seed)) {
                // main has no list reader yet (SIXTY-14's intList)
                foreach ($seed as $element) {
                    expect(is_scalar($element))->toBeTrue();
                }
            } else {
                $findings[] = "Key {$key} has unexpected seed type: ".gettype($seed);
            }
        } catch (\Throwable $e) {
            $findings[] = "Key {$key} threw: ".get_class($e).' '.$e->getMessage();
        }
    }

    expect($findings)->toBe([]);
});
