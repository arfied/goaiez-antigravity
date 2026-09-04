<?php

namespace App\Modules\X131\Domain;

class InterestEngine
{
    public function mergeInterest($existing, $inferred)
    {
        if ($existing['source'] === 'tenant') {
            return $existing;
        }

        return $inferred;
    }

    public function infer($data)
    {
        return ['confidence' => 0.85, 'source' => 'inferred'];
    }
}
