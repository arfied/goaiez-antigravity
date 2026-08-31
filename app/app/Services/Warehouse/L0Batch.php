<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Enums\DataClassification;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * One L0 object's worth of received traffic, before it is written.
 *
 * `GOAIEZ_PIXEL_MASTER_BUILD` §5.2: an L0 object *"contains the full received
 * payload plus receipt metadata"*. The two halves are kept strictly apart here
 * and the reason is the whole design:
 *
 *  - **The payload is opaque.** It is stored as the exact string the collector
 *    received, never decoded and re-encoded. Re-encoding somebody else's JSON
 *    is where an archive stops being faithful — key order, float precision and
 *    unicode escaping all change under a round trip, and every one of them
 *    changes the bytes while the values compare equal.
 *  - **The receipt metadata is ours**, so it goes through [[CanonicalJson]] and
 *    obeys its no-float rule.
 *
 * ⚠️ **`receivedAt` IS FIXED HERE AND NEVER RE-READ FROM THE CLOCK** — this is
 * the ruling at decision 4862. §5.3's L1 DDL carries an `ingested_at`, and a
 * column stamped with the wall clock *at derivation time* makes a rebuild differ
 * from the thing it rebuilt, by construction, forever. So the receipt time is
 * receipt metadata: it is decided once, archived in L0, and every later
 * derivation reads it back rather than asking the clock. The wall-clock fact
 * about *when a replay ran* is real and is recorded — on `etl_runs`, which is
 * not derived from L0 and is not part of the byte comparison.
 */
final readonly class L0Batch
{
    /**
     * @param  list<L0Receipt>  $receipts
     * @param  string|null  $ipHash  §11 row 8 (decision 5000s). Keyed HMAC of
     *                               the request's address — never the address
     *                               itself. `App\Services\Pixel\PixelEnrichment`
     *                               is the only caller expected to pass a real
     *                               one; every other constructor of this class,
     *                               including every fixture in this codebase's
     *                               own test suite, leaves it `null` — which is
     *                               archived as JSON `null`, never omitted.
     * @param  string  $browser  §12's UA-derived family — `chrome`, `safari`,
     *                           `firefox`, `edge`, `bot`, or `unknown`.
     * @param  string  $os  Same vocabulary — `windows`, `macos`, `linux`,
     *                      `android`, `ios`, `chromeos`, or `unknown`.
     */
    public function __construct(
        public string $batchId,
        public int $businessId,
        public DataClassification $dataClass,
        public CarbonImmutable $receivedAt,
        public array $receipts,
        public ?string $ipHash = null,
        public string $browser = 'unknown',
        public ?string $browserVersion = null,
        public string $os = 'unknown',
    ) {
        if ($receipts === []) {
            throw new RuntimeException(
                'An empty L0 batch would write an object with no lines, which is indistinguishable '
                .'from a truncated one on read. Nothing received is nothing archived.',
            );
        }
    }

    /**
     * The object key, which is also the partition.
     *
     * §5.2's layout, with `business=` where the specification writes
     * `tenant={uuid}`: this schema's tenant key is `businesses.id`, a bigint,
     * and `DATA-MODEL.md` uses `business_id` throughout — CLAUDE.md's
     * [[\App\Concerns\BelongsToTenant]] says never `tenant_id`.
     *
     * ⚠️ **`hour=` IS UTC AND SAYS SO IN THE FORMAT.** A prefix computed in the
     * server's local zone puts the same instant in two different partitions
     * either side of a DST change, and a range replay then reads one of them
     * twice and the other never.
     */
    public function path(): string
    {
        $utc = $this->receivedAt->utc();

        return 'class='.$this->dataClass->value
            .'/business='.$this->businessId
            .'/dt='.$utc->format('Y-m-d')
            .'/hour='.$utc->format('H')
            .'/'.$this->batchId.'.jsonl.gz';
    }

    /**
     * The object's contents: one canonical JSON line per receipt.
     *
     * ⚠️ **THE KEY ORDER BELOW IS THE FILE FORMAT.** It is written as an array
     * literal, in this order, and [[CanonicalJson::assertShape()]] holds it
     * there — see [[L0Line::keysFor()]]. ⚠️ **EVERY BATCH THIS BUILD WRITES USES
     * `config('warehouse.schema_version')`, WHATEVER `$ipHash`/`$browser`/`$os`
     * WERE PASSED.** A batch built with no enrichment still writes the version-2
     * shape, with `ip_hash` and `browser_version` archived as JSON `null` —
     * shape is a property of *when this was written*, not of what one caller
     * happened to know.
     */
    public function lines(): string
    {
        $utc = $this->receivedAt->utc();
        $schemaVersion = (int) config('warehouse.schema_version');

        $lines = [];
        $sequence = 0;

        foreach ($this->receipts as $receipt) {
            $row = [
                'schema_version' => $schemaVersion,
                'batch_id' => $this->batchId,
                'business_id' => $this->businessId,
                'data_class' => $this->dataClass->value,
                'received_at' => $utc->format(L0Line::TIMESTAMP_FORMAT),
                'receipt_seq' => $sequence,
                'receipt_id' => $receipt->receiptId,
            ];

            if ($schemaVersion >= 2) {
                $row['ip_hash'] = $this->ipHash;
                $row['browser'] = $this->browser;
                $row['browser_version'] = $this->browserVersion;
                $row['os'] = $this->os;
            }

            $row['payload'] = $receipt->payload;

            CanonicalJson::assertShape($row, L0Line::keysFor($schemaVersion), 'An L0 line');

            $lines[] = CanonicalJson::encode($row);
            $sequence++;
        }

        // ⚠️ A TRAILING NEWLINE, ALWAYS. JSONL readers differ on whether a final
        // line may be unterminated, and "sometimes present" is a byte
        // difference between two objects holding the same events.
        return implode("\n", $lines)."\n";
    }
}
