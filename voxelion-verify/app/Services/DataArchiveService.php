<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Restorable-archive layer for `data:purge`: before personal data is
 * deleted, every row is dumped to a JSON archive under
 * storage/app/archives/. Archives are restorable for RETENTION_DAYS,
 * then pruned. Archive contents are never logged or printed.
 */
class DataArchiveService
{
    public const RETENTION_DAYS = 31;

    private const DIR = 'archives';

    public function archivePath(): string
    {
        return storage_path('app/'.self::DIR);
    }

    /**
     * Dump every row of the given tables into a timestamped archive.
     *
     * @return array{path: string, tables: array<string, int>}
     */
    public function create(array $tables): array
    {
        File::ensureDirectoryExists($this->archivePath());

        $payload = ['created_at' => now()->toIso8601String(), 'tables' => []];
        $counts = [];
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $rows = DB::table($table)->get()->map(fn ($r) => (array) $r)->all();
            $payload['tables'][$table] = $rows;
            $counts[$table] = count($rows);
        }

        $path = $this->archivePath().'/purge-'.now()->format('Ymd-His').'.json';
        File::put($path, json_encode($payload));

        return ['path' => $path, 'tables' => $counts];
    }

    /**
     * List archives newest-first with their age and expiry status.
     *
     * @return array<int, array{path: string, created_at: Carbon, expired: bool}>
     */
    public function archives(): array
    {
        $out = [];
        foreach (File::glob($this->archivePath().'/*.json') as $path) {
            $out[] = [
                'path' => $path,
                'created_at' => Carbon::createFromTimestamp(File::lastModified($path)),
                'expired' => Carbon::createFromTimestamp(File::lastModified($path))
                    ->lt(now()->subDays(self::RETENTION_DAYS)),
            ];
        }
        usort($out, fn ($a, $b) => $b['created_at']->timestamp <=> $a['created_at']->timestamp);

        return $out;
    }

    public function isExpired(string $path): bool
    {
        return Carbon::createFromTimestamp(File::lastModified($path))
            ->lt(now()->subDays(self::RETENTION_DAYS));
    }

    /**
     * Insert archived rows back (existing PKs are ignored). Caller wraps
     * this in its own FK-off transaction.
     *
     * @return array<string, int> rows restored per table
     */
    public function restore(string $path, array $tables): array
    {
        $payload = json_decode(File::get($path), true);
        $archived = $payload['tables'] ?? [];

        $restored = [];
        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! isset($archived[$table])) {
                continue;
            }
            $count = 0;
            foreach ($archived[$table] as $row) {
                $count += DB::table($table)->insertOrIgnore((array) $row);
            }
            $restored[$table] = $count;
        }

        return $restored;
    }

    /** Delete archives older than RETENTION_DAYS. Returns count removed. */
    public function prune(): int
    {
        $removed = 0;
        foreach ($this->archives() as $archive) {
            if ($archive['expired']) {
                File::delete($archive['path']);
                $removed++;
            }
        }

        return $removed;
    }
}
