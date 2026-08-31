<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | The L0 landing disk
    |--------------------------------------------------------------------------
    |
    | `GOAIEZ_PIXEL_MASTER_BUILD` §5.2 puts L0 on Cloudflare R2, and CLAUDE.md
    | §"Pixel / warehouse" keeps it there through the collapse into Laravel:
    | "L0 stays the durable, replayable archive: gzipped JSONL in Cloudflare R2
    | (S3-compatible, so Laravel's `s3` driver)".
    |
    | Named here rather than hardcoded so the test suite can point it at a fake
    | disk without the archive knowing. `s3` is the production answer and the
    | R2 credentials arrive through AWS_ENDPOINT / AWS_BUCKET, which is why
    | SUBPROCESSOR-INVENTORY.md's outbound scanner cannot see R2's host and says
    | so explicitly.
    |
    */

    'l0_disk' => env('WAREHOUSE_L0_DISK', 's3'),

    /*
    |--------------------------------------------------------------------------
    | The canonical schema version stamped on every L0 line
    |--------------------------------------------------------------------------
    |
    | ⚠️ THIS IS NOT A KNOB. It is a constant that lives in config so a replay of
    | old objects can branch on it, and it must only ever be incremented in a
    | commit that also teaches the L1 derivation to read the old version. An L0
    | archive is seven years deep (§5.2); the derivation has to keep reading
    | every version it ever wrote, forever, or "rebuildable from L0" stops being
    | true for everything written before the change.
    |
    | ⚠️ BUMPED 1 → 2 FOR §11 ROW 8's ENRICHMENT (decision 5000s). A version-2
    | line carries `ip_hash`, `browser`, `browser_version` and `os` ahead of
    | `payload`; `App\Services\Warehouse\L0Line::keysFor()` is where both
    | versions' shapes are written down, and `App\Services\Warehouse\
    | L1Derivation::rows()` fills the four new `l1_events` columns with `null`
    | when it reads a version-1 line. No version-1 object has ever been written
    | in production — nothing wrote L0 at all before this collector existed
    | (decision 4861) — so this is the discipline the archive's own invariant
    | asks for, proven rather than merely stated.
    |
    */

    'schema_version' => 2,

];
