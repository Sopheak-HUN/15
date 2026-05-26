<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Geo\Commune;
use App\Models\Geo\District;
use App\Models\Geo\Province;
use App\Models\Geo\Village;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Imports Cambodia's administrative geography into the local provinces /
 * districts / communes / villages tables.
 *
 * Source: MEF open data portal — four per-level datasets published by MLMUPC.
 *   https://data.mef.gov.kh/km/datasets/pd_66a8603700604c000123e144  (provinces)
 *   https://data.mef.gov.kh/km/datasets/pd_66a8603800604c000123e145  (districts)
 *   https://data.mef.gov.kh/km/datasets/pd_66a8603900604c000123e146  (communes)
 *   https://data.mef.gov.kh/km/datasets/pd_66a8603a00604c000123e147  (villages)
 *
 * Flow (per level):
 *   1. If database/seed-data/{level}.csv exists → use it.
 *   2. Otherwise: paginate the MEF JSON endpoint and write the CSV.
 *   3. Parse the CSV → upsert into the matching DB table.
 *
 * Flags:
 *   --level=villages  Limit to one level (parents must already exist in DB).
 *   --refresh         Force re-download even if the cached CSV exists.
 *   --from-mef        Skip CSV caching; read live from the MEF API each run.
 *
 * Idempotent — uses upsert on the `code` PK so re-runs update renamed rows
 * without breaking foreign keys.
 */
final class ImportGeo extends Command
{
    protected $signature = 'geo:import
        {--level= : Only import one level (provinces|districts|communes|villages)}
        {--refresh : Force re-download the CSV from MEF even if cached}
        {--from-mef : Bypass CSV cache and read live from MEF on every run}';

    protected $description = 'Import Cambodia geography (downloads MEF CSVs on first run, then imports into DB)';

    private const SEED_DIR = 'database/seed-data';

    /**
     * Per-level config: MEF dataset id, model, CSV filename, and the
     * column order to use when writing/reading the CSV. The first column
     * for each level is the row's PK (`code`); the rest map to DB columns.
     */
    private const LEVELS = [
        'provinces' => [
            'dataset'      => 'pd_66a8603700604c000123e144',
            'model'        => Province::class,
            'csv'          => 'provinces.csv',
            'csv_columns'  => ['province_code', 'province_kh', 'province_en'],
            'field_map'    => [
                'code'    => 'province_code',
                'name_kh' => 'province_kh',
                'name_en' => 'province_en',
            ],
        ],
        'districts' => [
            'dataset'      => 'pd_66a8603800604c000123e145',
            'model'        => District::class,
            'csv'          => 'districts.csv',
            'csv_columns'  => ['province_code', 'district_code', 'district_kh', 'district_en'],
            'field_map'    => [
                'code'          => 'district_code',
                'province_code' => 'province_code',
                'name_kh'       => 'district_kh',
                'name_en'       => 'district_en',
            ],
        ],
        'communes' => [
            'dataset'      => 'pd_66a8603900604c000123e146',
            'model'        => Commune::class,
            'csv'          => 'communes.csv',
            'csv_columns'  => ['province_code', 'district_code', 'commune_code', 'commune_kh', 'commune_en'],
            'field_map'    => [
                'code'          => 'commune_code',
                'district_code' => 'district_code',
                'name_kh'       => 'commune_kh',
                'name_en'       => 'commune_en',
            ],
        ],
        'villages' => [
            'dataset'      => 'pd_66a8603a00604c000123e147',
            'model'        => Village::class,
            'csv'          => 'villages.csv',
            'csv_columns'  => ['province_code', 'district_code', 'commune_code', 'village_code', 'village_kh', 'village_en'],
            'field_map'    => [
                'code'         => 'village_code',
                'commune_code' => 'commune_code',
                'name_kh'      => 'village_kh',
                'name_en'      => 'village_en',
            ],
        ],
    ];

    public function handle(): int
    {
        $only = $this->option('level');
        $allLevels = array_keys(self::LEVELS);
        if ($only !== null && !isset(self::LEVELS[$only])) {
            $this->error("Unknown level: {$only}");
            return self::FAILURE;
        }
        $levels = $only ? [$only] : $allLevels;

        $seedDir = base_path(self::SEED_DIR);
        if (!is_dir($seedDir) && !mkdir($seedDir, 0775, true)) {
            $this->error("Cannot create directory: {$seedDir}");
            return self::FAILURE;
        }

        foreach ($levels as $level) {
            $cfg = self::LEVELS[$level];
            $csvPath = "{$seedDir}/{$cfg['csv']}";

            // Step 1: source the rows (cached CSV by default; live MEF otherwise).
            if ($this->option('from-mef')) {
                $this->info("Fetching {$level} live from MEF...");
                $rows = $this->fetchAllPages($cfg['dataset']);
            } else {
                if ($this->option('refresh') || !is_file($csvPath)) {
                    $this->info("Downloading {$level} from MEF → {$csvPath}");
                    $written = $this->downloadLevelCsv($cfg['dataset'], $cfg['csv_columns'], $csvPath);
                    if ($written === 0) {
                        $this->error("  download failed (0 rows written)");
                        return self::FAILURE;
                    }
                    $this->line("  wrote {$written} rows");
                } else {
                    $this->line("Using cached {$level} CSV: {$csvPath} (pass --refresh to re-download)");
                }
                $rows = $this->readLevelCsv($csvPath, $cfg['csv_columns']);
            }

            $this->line("  source has " . count($rows) . " rows");

            // Step 2: map MEF row shape → DB row shape and dedupe by PK.
            $mapped = [];
            foreach ($rows as $row) {
                $out = [];
                foreach ($cfg['field_map'] as $dbCol => $srcCol) {
                    $out[$dbCol] = $row[$srcCol] ?? null;
                }
                if (!empty($out['code'])) {
                    $mapped[$out['code']] = $out;
                }
            }
            $mapped = array_values($mapped);

            // Step 2b: drop rows whose parent code isn't in the DB. MEF
            // datasets occasionally reference codes across levels that don't
            // line up (e.g. a village under a commune that's missing from
            // the communes dataset). Without this filter, the upsert hits a
            // foreign-key violation and the entire chunk rolls back.
            $parentMap = [
                'districts' => ['column' => 'province_code', 'model' => Province::class],
                'communes'  => ['column' => 'district_code', 'model' => District::class],
                'villages'  => ['column' => 'commune_code',  'model' => Commune::class],
            ];
            if (isset($parentMap[$level])) {
                $parentCol = $parentMap[$level]['column'];
                $validParents = $parentMap[$level]['model']::pluck('code')->flip();
                $orphanCodes = [];
                $kept = [];
                foreach ($mapped as $row) {
                    if (isset($validParents[$row[$parentCol]])) {
                        $kept[] = $row;
                    } else {
                        $orphanCodes[$row[$parentCol]] = true;
                    }
                }
                $orphans = count($mapped) - count($kept);
                $mapped = $kept;
                if ($orphans > 0) {
                    $sample = implode(', ', array_slice(array_keys($orphanCodes), 0, 5));
                    $this->warn("  skipped {$orphans} orphan {$level} — {$parentCol} not in DB (e.g. {$sample})");
                }
            }

            // Step 3: bulk upsert in chunks (Postgres rejects duplicate PKs
            // in a single ON CONFLICT statement — dedupe above prevents that).
            $this->info("  upserting into {$cfg['model']}...");
            $upsertStart = microtime(true);
            DB::transaction(function () use ($cfg, $mapped) {
                if (empty($mapped)) return;
                $columns = array_keys($mapped[0]);
                $updateCols = array_values(array_diff($columns, ['code']));
                foreach (array_chunk($mapped, 1000) as $chunk) {
                    $cfg['model']::upsert($chunk, ['code'], $updateCols);
                }
            });
            $this->line("  upsert finished in " . number_format(microtime(true) - $upsertStart, 2) . "s");
        }

        $this->info('Done.');
        return self::SUCCESS;
    }

    /**
     * Paginate the MEF JSON endpoint for a single dataset and write the rows
     * straight out to a CSV in the specified column order. UTF-8 BOM is
     * prepended so the file opens correctly in Excel.
     *
     * NOTE: withoutVerifying() bypasses SSL chain validation. Drop it once
     * ca-certificates is baked into the container image.
     */
    private function downloadLevelCsv(string $datasetId, array $columns, string $destPath): int
    {
        $base = "https://data.mef.gov.kh/api/v1/public-datasets/{$datasetId}/json";
        $partialPath = $destPath . '.partial';
        $cursorPath  = $destPath . '.cursor.json';

        // Step 1: figure out the dataset shape from page 1 (always re-fetch).
        $first = $this->fetchPage($base, 1);
        if ($first === null || !isset($first['items'], $first['total_pages'], $first['total_items'])) {
            $this->error("  could not fetch page 1");
            return 0;
        }
        $totalPages = (int) $first['total_pages'];
        $totalItems = (int) $first['total_items'];

        // Step 2: figure out where to resume from. A cursor exists only when
        // a previous run made it past page 1 but didn't finish. It must match
        // the dataset id AND the totals, otherwise the partial file is from
        // a different snapshot and we start clean.
        $resumeFrom = 1;
        $cursor = is_file($cursorPath) ? json_decode((string) @file_get_contents($cursorPath), true) : null;
        if (
            is_array($cursor)
            && ($cursor['dataset_id']  ?? null) === $datasetId
            && ($cursor['total_pages'] ?? null) === $totalPages
            && ($cursor['total_items'] ?? null) === $totalItems
            && is_int($cursor['last_written'] ?? null)
            && is_file($partialPath)
        ) {
            $resumeFrom = $cursor['last_written'] + 1;
            $this->line("  resuming from page {$resumeFrom} of {$totalPages} (partial cache found)");
        } else {
            // Fresh start: open partial file with BOM + header, write page 1.
            @unlink($partialPath);
            $fp = fopen($partialPath, 'w');
            if ($fp === false) { $this->error("  cannot open {$partialPath}"); return 0; }
            fwrite($fp, "\xEF\xBB\xBF");
            fputcsv($fp, $columns);
            $this->appendItems($fp, $first['items'], $columns);
            fclose($fp);
            $this->writeCursor($cursorPath, $datasetId, $totalPages, $totalItems, 1);
            $resumeFrom = 2;
        }

        // Step 3: fetch remaining pages, appending to the partial file as
        // each one succeeds. The cursor is updated after every page so a
        // crash mid-run loses at most one page of work.
        if ($resumeFrom <= $totalPages) {
            $fp = fopen($partialPath, 'a');
            if ($fp === false) { $this->error("  cannot reopen {$partialPath}"); return 0; }

            $bar = $this->output->createProgressBar($totalPages);
            $bar->advance($resumeFrom - 1);

            for ($p = $resumeFrom; $p <= $totalPages; $p++) {
                $resp = $this->fetchPage($base, $p);
                if ($resp === null || !isset($resp['items'])) {
                    fclose($fp);
                    $bar->finish();
                    $this->newLine();
                    $this->error("  page {$p} of {$totalPages} failed after retries");
                    $this->warn("  partial download saved — re-run the same command to resume from here");
                    return 0;
                }
                $this->appendItems($fp, $resp['items'], $columns);
                $this->writeCursor($cursorPath, $datasetId, $totalPages, $totalItems, $p);
                $bar->advance();
                usleep(150_000); // 150ms pacing — under Cloudflare's burst threshold
            }
            $bar->finish();
            $this->newLine();
            fclose($fp);
        }

        // Step 4: verify completeness (row count in partial must equal MEF's
        // total_items + 1 for the header), then atomically promote to final.
        $rowCount = $this->countCsvRows($partialPath) - 1; // minus header
        if ($rowCount !== $totalItems) {
            $this->error("  expected {$totalItems} rows, partial has {$rowCount} — keeping partial+cursor for next retry");
            return 0;
        }

        if (!@rename($partialPath, $destPath)) {
            $this->error("  could not promote {$partialPath} to {$destPath}");
            return 0;
        }
        @unlink($cursorPath);

        return $rowCount;
    }

    /** @param array<int, array<string, mixed>> $items */
    private function appendItems($fp, array $items, array $columns): void
    {
        foreach ($items as $r) {
            $row = [];
            foreach ($columns as $c) { $row[] = $r[$c] ?? ''; }
            fputcsv($fp, $row);
        }
    }

    private function writeCursor(string $path, string $datasetId, int $totalPages, int $totalItems, int $lastWritten): void
    {
        file_put_contents($path, json_encode([
            'dataset_id'   => $datasetId,
            'total_pages'  => $totalPages,
            'total_items'  => $totalItems,
            'last_written' => $lastWritten,
            'updated_at'   => date('c'),
        ], JSON_PRETTY_PRINT));
    }

    private function countCsvRows(string $path): int
    {
        $count = 0;
        $h = fopen($path, 'r');
        if ($h === false) return 0;
        while (fgets($h) !== false) { $count++; }
        fclose($h);
        return $count;
    }

    /**
     * Fetch one MEF page with bounded retries + exponential backoff. Returns
     * the decoded JSON envelope on success, or null after the final attempt.
     *
     * @return array<string, mixed>|null
     */
    private function fetchPage(string $base, int $page, int $maxAttempts = 6): ?array
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $resp = Http::withoutVerifying()
                    ->timeout(60)
                    ->retry(0)  // we manage retries ourselves
                    ->get($base, ['page' => $page, 'page_size' => 200]);

                if ($resp->successful()) {
                    $data = $resp->json();
                    if (is_array($data) && isset($data['items'])) {
                        return $data;
                    }
                }
                $status = $resp->status();
                $this->warn("  page {$page} attempt {$attempt}/{$maxAttempts}: HTTP {$status}");
            } catch (\Throwable $e) {
                $this->warn("  page {$page} attempt {$attempt}/{$maxAttempts}: " . $e->getMessage());
            }

            if ($attempt < $maxAttempts) {
                // Exponential backoff capped at 30s: 1s → 2s → 4s → 8s → 16s.
                $sleep = min(30, 2 ** ($attempt - 1));
                sleep($sleep);
            }
        }
        return null;
    }

    /**
     * Read a per-level CSV back into MEF-shaped associative rows. Strips the
     * UTF-8 BOM from the first header cell if present.
     *
     * @return array<int, array<string, string>>
     */
    private function readLevelCsv(string $path, array $expectedColumns): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) return [];

        $header = fgetcsv($handle);
        if ($header === false) { fclose($handle); return []; }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);

        $out = [];
        while (($row = fgetcsv($handle)) !== false) {
            $combined = array_combine($header, array_pad($row, count($header), null));
            if ($combined === false) continue;
            $out[] = $combined;
        }
        fclose($handle);
        return $out;
    }

    /**
     * Live MEF fetch (no CSV touch). Same as downloadLevelCsv but returns
     * rows in memory. Used by --from-mef.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchAllPages(string $datasetId): array
    {
        $base = "https://data.mef.gov.kh/api/v1/public-datasets/{$datasetId}/json";
        $http = fn () => Http::withoutVerifying()->timeout(60);

        $first = $http()->get($base, ['page' => 1, 'page_size' => 200])->json();
        if (!is_array($first) || !isset($first['items'], $first['total_pages'])) {
            return [];
        }
        $rows = $first['items'];
        $bar = $this->output->createProgressBar((int) $first['total_pages']);
        $bar->advance();
        for ($p = 2; $p <= (int) $first['total_pages']; $p++) {
            $resp = $http()->get($base, ['page' => $p, 'page_size' => 200])->json();
            if (is_array($resp) && isset($resp['items'])) {
                $rows = array_merge($rows, $resp['items']);
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        return $rows;
    }
}
