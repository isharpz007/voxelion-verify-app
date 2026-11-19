<?php

namespace App\Console\Commands;

use App\Services\DataArchiveService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Wipe all volunteer/participant data for a clean-slate test run.
 *
 * Deletes every row from personal-data tables (keeping structure), removes
 * uploaded media under storage/app, truncates logs (which may hold email
 * addresses), and clears caches. Refuses to run in production. Never prints
 * email addresses or other personal data — counts only.
 */
class DataPurge extends Command
{
    protected $signature = 'data:purge
                            {--force : Skip the interactive confirmation (still blocked in production)}
                            {--dry-run : Report what would be deleted without deleting anything}
                            {--no-archive : Permanently delete with no restorable archive}';

    protected $description = 'Delete all user data, media files, sessions, cache, queue jobs and logs for a clean-slate test run';

    /** Tables cleared in child-before-parent order. `migrations` is never touched. */
    public const TABLES = [
        'sessions',
        'jobs',
        'job_batches',
        'failed_jobs',
        'cache',
        'cache_locks',
        'audit_events',
        'deletion_requests',
        'reward_submissions',
        'completion_codes',
        'verification_results',
        'media_assets',
        'otps',
        'consents',
        'verification_sessions',
        'participants',
    ];

    /** storage/app subdirs whose contents are user data (contents removed, dir kept). */
    private const FILE_DIRS = ['media', 'private', 'public'];

    public function handle(): int
    {
        if (config('app.env') === 'production') {
            $this->error('data:purge refuses to run when APP_ENV=production.');

            return self::FAILURE;
        }

        $counts = $this->rowCounts();
        $files = $this->fileCounts();

        $this->components->info('Rows per table:');
        foreach ($counts as $table => $count) {
            $this->line(sprintf('  %-24s %d', $table, $count));
        }
        $this->components->info('Files per location:');
        foreach ($files as $path => $count) {
            $this->line(sprintf('  %-30s %d', $path, $count));
        }

        if ($this->option('dry-run')) {
            $this->components->info('Dry run — nothing was deleted.');

            return self::SUCCESS;
        }

        if (! $this->option('force')
            && $this->ask('This permanently deletes ALL volunteer data. Type DELETE to confirm') !== 'DELETE') {
            $this->components->warn('Aborted — nothing was deleted.');

            return self::FAILURE;
        }

        if ($this->option('no-archive')) {
            $this->components->warn('No archive — deletion is permanent.');
        } else {
            $archive = app(DataArchiveService::class)->create(self::TABLES);
            $pruned = app(DataArchiveService::class)->prune();
            $this->components->info(sprintf(
                'Archived to %s (restorable for %d days via data:restore)%s',
                basename($archive['path']),
                DataArchiveService::RETENTION_DAYS,
                $pruned > 0 ? " — pruned {$pruned} expired archive(s)" : '',
            ));
        }

        // SQLite cannot toggle FK checks inside a transaction — off, then on.
        DB::connection()->statement('PRAGMA foreign_keys = OFF');
        try {
            DB::transaction(function () {
                foreach (self::TABLES as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->delete();
                    }
                }

                try {
                    DB::table('sqlite_sequence')->whereIn('name', self::TABLES)->delete();
                } catch (\Throwable) {
                    // sqlite_sequence only exists with AUTOINCREMENT/integer PKs.
                }
            });
        } finally {
            DB::connection()->statement('PRAGMA foreign_keys = ON');
        }

        $filesDeleted = 0;
        foreach (self::FILE_DIRS as $dir) {
            $filesDeleted += $this->cleanDirectory(storage_path('app/'.$dir));
        }

        // Truncate rather than delete — a running server holds logs open on Windows.
        $logsCleared = 0;
        foreach (File::glob(storage_path('logs/*.log')) as $log) {
            file_put_contents($log, '');
            $logsCleared++;
        }

        $this->callSilently('cache:clear');
        $this->callSilently('config:clear');
        $this->callSilently('view:clear');

        $this->components->info('Purge complete:');
        foreach ($counts as $table => $count) {
            $remaining = Schema::hasTable($table) ? DB::table($table)->count() : 'n/a';
            $this->line(sprintf('  %-24s %d deleted, %s remaining', $table, $count, $remaining));
        }
        $this->line(sprintf('  %-24s %d', 'files deleted', $filesDeleted));
        $this->line(sprintf('  %-24s %d', 'logs truncated', $logsCleared));

        return self::SUCCESS;
    }

    /** @return array<string, int> */
    private function rowCounts(): array
    {
        $counts = [];
        foreach (self::TABLES as $table) {
            $counts[$table] = Schema::hasTable($table) ? DB::table($table)->count() : 0;
        }

        return $counts;
    }

    /** @return array<string, int> */
    private function fileCounts(): array
    {
        $out = [];
        foreach (self::FILE_DIRS as $dir) {
            $out['storage/app/'.$dir] = $this->countFiles(storage_path('app/'.$dir));
        }
        $out['storage/logs/*.log'] = count(File::glob(storage_path('logs/*.log')));

        return $out;
    }

    private function countFiles(string $dir): int
    {
        if (! File::isDirectory($dir)) {
            return 0;
        }

        return count(array_filter(
            File::allFiles($dir),
            fn ($f) => ! str_starts_with($f->getFilename(), '.'),
        ));
    }

    /** Delete contents of $dir (non-dot files + subdirectories), keep $dir itself. */
    private function cleanDirectory(string $dir): int
    {
        if (! File::isDirectory($dir)) {
            return 0;
        }

        $count = 0;
        foreach (File::files($dir) as $file) {
            if (str_starts_with($file->getFilename(), '.')) {
                continue;
            }
            File::delete($file);
            $count++;
        }
        foreach (File::directories($dir) as $sub) {
            $count += $this->countFiles($sub);
            File::deleteDirectory($sub);
        }

        return $count;
    }
}
