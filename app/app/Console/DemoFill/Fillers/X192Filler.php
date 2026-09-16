<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use Illuminate\Support\Facades\DB;

class X192Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-192';
    }

    public function fill(Business $business): int
    {
        if (DB::table('directory_memberships')->where('business_id', $business->id)->where('directory_name', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        DB::table('directory_memberships')->insert([
            ['business_id' => $business->id, 'directory_name' => self::MARKER.'Yelp', 'directory_url' => 'https://yelp.com', 'is_noindex' => false],
            ['business_id' => $business->id, 'directory_name' => self::MARKER.'YellowPages', 'directory_url' => 'https://yp.com', 'is_noindex' => true],
        ]);

        return 2;
    }

    public function purge(Business $business): int
    {
        return DB::table('directory_memberships')->where('business_id', $business->id)->where('directory_name', 'like', self::MARKER.'%')->delete();
    }
}
