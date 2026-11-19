<?php

namespace App\Console\Commands;

use App\Services\DataArchiveService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Restore personal data from an archive created by `data:purge`.
 * Only works inside the RETENTION_DAYS window; expired archives are
 * pruned by the next data:purge run. Blocked in production.
 */
class DataRestore extends Command
{
    protected $signature = 'data:restore
                            {--file= : Archive filename in storage/app/archives (defaults to newest)}
                            {--force : Skip the interactive confirmation}';

    protected $description = 'Restore user data from a data:purge archive within the retention window';

    public function handle(DataArchiveService $service): int
    {
        if (config('app.env') === 'production') {
            $this->error('data:restore refuses to run when APP_ENV=production.');

            return self::FAILURE;
        }

        $archives = $service->archives();
        if ($archives === []) {
            $this->components->warn('No archives found — nothing to restore.');

            return self::SUCCESS;
        }

        $chosen = $this->option('file')
            ? $service->archivePath().'/'.$this->option('file')
            : $archives[0]['path'];

        if (! file_exists($chosen)) {
            $this->error("Archive not found: {$this->option('file')}");
            $this->listArchives($archives);

            return self::FAILURE;
        }

        if ($service->isExpired($chosen)) {
            $this->error(sprintf(
                'That archive is older than %d days and can no longer be restored.',
                DataArchiveService::RETENTION_DAYS,
            ));

            return self::FAILURE;
        }

        if (! $this->option('force')
            && $this->ask(sprintf('Restore %s? Type RESTORE to confirm', basename($chosen))) !== 'RESTORE') {
            $this->components->warn('Aborted — nothing was restored.');

            return self::FAILURE;
        }

        DB::connection()->statement('PRAGMA foreign_keys = OFF');
        try {
            $restored = DB::transaction(
                // Parents before children — reverse of the purge order.
                fn () => $service->restore($chosen, array_reverse(DataPurge::TABLES)),
            );
        } finally {
            DB::connection()->statement('PRAGMA foreign_keys = ON');
        }

        $this->components->info('Restored:');
        foreach ($restored as $table => $count) {
            $this->line(sprintf('  %-24s %d', $table, $count));
        }

        return self::SUCCESS;
    }

    /** @param array<int, array{path: string}> $archives */
    private function listArchives(array $archives): void
    {
        foreach ($archives as $archive) {
            $this->line('  '.basename($archive['path']));
        }
    }
}
