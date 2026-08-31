<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Enums\AiTask;
use App\Enums\KnowledgeSourceStatus;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeSource;
use App\Services\Ai\AiRouter;
use App\Services\Ai\EmbeddingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Reads one uploaded document, chunks it, embeds it and stores the vectors.
 *
 * ⛔ **THE WRITER `knowledge_sources` AND `knowledge_chunks` SHIPPED WITHOUT.**
 * Both tables have existed since Stage 0 with a factory, an isolation test and a
 * `nearestTo()` carrying a carefully-argued tenant predicate — and nothing in
 * `app/` that put a row in either. That is the failure `CLAUDE.md` records
 * sixteen times: *an isolation test passes perfectly against a table nothing
 * writes, so the suite is green and the feature is inert.* This class is the
 * writer; `KnowledgeRetriever` is the reader.
 *
 * ## The order of operations, and why it is that order
 *
 *   1. read the file          — a missing object is theirs to fix, not a retry
 *   2. extract text           — a PDF is refused here, by its bytes (DocumentText)
 *   3. chunk                  — deterministic, no vendor, no database
 *   4. embed the whole batch  — one router call, one cap check, one ledger row
 *   5. check the dimension    — before anything is written
 *   6. replace the chunks     — in one transaction
 *
 * ⚠️ **THE VENDOR CALL IS BEFORE THE FIRST WRITE AND THE WRITE IS ATOMIC.** The
 * inverse — write chunks, then embed them one at a time — is the shape that
 * leaves a source half-embedded when a worker dies: some chunks retrievable,
 * some with a null vector, and a retrieval that silently answers from half a
 * document. Everything is bought before anything is committed.
 *
 * ⚠️ **RE-INGEST REPLACES RATHER THAN APPENDS.** A source ingested twice would
 * otherwise hold two copies of every chunk, which doubles the bill, doubles the
 * storage, and makes retrieval return the same fact twice — crowding a second,
 * different fact out of a five-result window. The delete and the insert share
 * one transaction so there is no instant where a source has no chunks at all.
 *
 * ⚠️ **THE DIMENSION IS CHECKED, NOT ASSUMED** (AiModel::embeddingDimensions()).
 * A model swap that changes the vector length would otherwise fail at the
 * `pgvector` column with an error naming a column rather than a model, halfway
 * through a corpus and after the whole batch had been paid for.
 */
final class KnowledgeIngestor
{
    /**
     * How many chunks one source may produce.
     *
     * ⚠️ **A CEILING RATHER THAN A CAP ON THE FILE SIZE ALONE, BECAUSE THE TWO
     * ARE NOT THE SAME BOUND.** The upload screen limits the file to 2MB, but
     * 2MB of dense plain text is well over a thousand chunks — one owner, one
     * upload, one batch of a thousand vendor calls' worth of tokens. The
     * per-tenant monthly cap would catch it eventually; catching it here means
     * catching it before the money is spent rather than after.
     */
    public const int MAX_CHUNKS = 400;

    public function __construct(
        private readonly AiRouter $router,
        private readonly DocumentChunker $chunker = new DocumentChunker,
        private readonly DocumentText $text = new DocumentText,
    ) {}

    public function ingest(KnowledgeSource $source): IngestOutcome
    {
        $path = $source->file_path;

        if ($path === null || $path === '') {
            return IngestOutcome::unreadable('no_file');
        }

        $disk = Storage::disk(config('filesystems.default'));

        if (! $disk->exists($path)) {
            // Theirs in the sense that retrying will not help: the object is
            // gone. Marked unreadable rather than failed so the sweep does not
            // pick it up forever.
            return IngestOutcome::unreadable('file_missing');
        }

        $bytes = (string) $disk->get($path);

        $extracted = $this->text->extract($bytes);

        if ($extracted === null) {
            return IngestOutcome::unreadable($this->text->refusalFor($bytes));
        }

        $chunks = $this->chunker->chunk($extracted);

        if ($chunks === []) {
            return IngestOutcome::unreadable('no_readable_text');
        }

        if (count($chunks) > self::MAX_CHUNKS) {
            return IngestOutcome::unreadable('too_large');
        }

        $response = $this->router->embed(new EmbeddingRequest(
            task: AiTask::KnowledgeEmbedding,
            inputs: $chunks,
        ));

        if (! $response->isUsable()) {
            // Ours. A cap, an outage, a missing credential — all clear on their
            // own or with an operator's help, so this is retryable.
            return IngestOutcome::failed($response->failureReason ?? 'no_vectors');
        }

        // ⚠️ THE SHORT-BATCH CHECK, AND IT IS NOT PARANOIA. Zipping a shorter
        // vector list against the chunk list by position stores chunk four's
        // text under chunk three's vector — a corpus that retrieves confidently
        // wrong answers with nothing anywhere to notice. See
        // EmbeddingResponse::covers().
        if (! $response->covers(count($chunks))) {
            return IngestOutcome::failed('vector_count_mismatch');
        }

        $expected = $response->model->embeddingDimensions();

        foreach ($response->vectors as $vector) {
            if ($expected === null || count($vector) !== $expected) {
                return IngestOutcome::failed('vector_dimension_mismatch');
            }
        }

        $this->replaceChunks($source, $chunks, $response->vectors);

        $source->forceFill([
            'status' => KnowledgeSourceStatus::Ingested,
            'last_ingested_at' => now(),
            'checksum' => hash('sha256', $extracted),
            'version' => $source->version + 1,
        ])->save();

        return IngestOutcome::ingested(count($chunks));
    }

    /**
     * @param  list<string>  $chunks
     * @param  list<list<float>>  $vectors
     */
    private function replaceChunks(KnowledgeSource $source, array $chunks, array $vectors): void
    {
        DB::transaction(function () use ($source, $chunks, $vectors): void {
            // Through the relationship, so the tenant scope applies to the
            // delete as well as to the insert. A raw `DB::table(...)->where(
            // 'source_id', ...)` would reach past the global scope, and RLS
            // would be the only thing left holding the line.
            $source->chunks()->delete();

            foreach ($chunks as $index => $chunk) {
                // ⚠️ `create()` RATHER THAN `insert()`, AND IT COSTS A ROUND TRIP
                // PER CHUNK ON PURPOSE. `BelongsToTenant` fills `business_id`
                // from context on the model's `creating` event, and a bulk
                // `insert()` fires no model events at all — so the fast version
                // writes rows with a null tenant key against a NOT NULL column,
                // or worse, whatever a hand-written array happened to say.
                KnowledgeChunk::query()->create([
                    'source_id' => $source->id,
                    'content' => $chunk,
                    'token_count' => $this->chunker->estimateTokens($chunk),
                    // pgvector takes its literal as a bracketed list, exactly as
                    // KnowledgeChunk::nearestTo() builds one for the query side.
                    'embedding' => '['.implode(',', $vectors[$index]).']',
                    'metadata' => ['position' => $index],
                    'created_at' => now(),
                ]);
            }
        });
    }
}
