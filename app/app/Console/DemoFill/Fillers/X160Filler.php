<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X160\Models\Document;

class X160Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-160';
    }

    public function fill(Business $business): int
    {
        if (Document::where('business_id', $business->id)->where('title', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        Document::create([
            'business_id' => $business->id,
            'title' => self::MARKER.'2026 price list',
            'sha256_hash' => 'demo0001'.str_repeat('b', 56),
            'status' => 'confirmed',
            'mime_type' => 'application/pdf',
        ]);

        Document::create([
            'business_id' => $business->id,
            'title' => self::MARKER.'Ridgeline service agreement',
            'sha256_hash' => 'demo0002'.str_repeat('c', 56),
            'status' => 'ingested',
            'mime_type' => 'application/pdf',
        ]);

        return 2;
    }

    public function purge(Business $business): int
    {
        return Document::where('business_id', $business->id)
            ->where('title', 'like', self::MARKER.'%')
            ->delete();
    }
}
