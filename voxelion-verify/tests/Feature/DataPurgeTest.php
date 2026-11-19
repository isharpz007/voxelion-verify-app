<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DataPurgeTest extends TestCase
{
    use RefreshDatabase;

    private const PERSONAL_TABLES = [
        'participants', 'verification_sessions', 'consents', 'otps',
        'media_assets', 'verification_results', 'completion_codes',
        'reward_submissions', 'deletion_requests', 'audit_events',
        'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Archives live on the real filesystem — clear leftovers so each
        // test starts clean regardless of prior failures.
        foreach (File::glob(storage_path('app/archives/*.json')) as $f) {
            File::delete($f);
        }
    }

    private function seedPersonalData(): void
    {
        $this->driveToPhoto($this->beginSession());
    }

    /** @test */
    public function it_purges_all_personal_data_and_files_but_keeps_structure_and_keys(): void
    {
        $this->seedPersonalData();

        $mediaDir = storage_path('app/media/media/test-participant');
        File::ensureDirectoryExists($mediaDir);
        File::put($mediaDir.'/selfie.jpg', 'fake-image');
        File::put(storage_path('logs/laravel.log'), "log line that may contain an email\n");

        $this->assertGreaterThan(0, DB::table('participants')->count());
        $this->assertGreaterThan(0, DB::table('otps')->count());

        $this->artisan('data:purge', ['--force' => true])->assertExitCode(0);

        foreach (self::PERSONAL_TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "table missing: {$table}");
            $this->assertSame(0, DB::table($table)->count(), "rows remain in {$table}");
        }

        $this->assertTrue(Schema::hasTable('migrations'));
        $this->assertGreaterThan(0, DB::table('migrations')->count());
        $this->assertDirectoryDoesNotExist($mediaDir);
        $this->assertFileExists(storage_path('app/keys/opinionpadi_public.pem'));
        $this->assertFileExists(storage_path('app/keys/opinionpadi_private.pem'));
        $this->assertSame('', file_get_contents(storage_path('logs/laravel.log')));

        foreach (File::glob(storage_path('app/archives/*.json')) as $f) {
            File::delete($f);
        }
    }

    /** @test */
    public function purge_archives_data_and_restore_brings_it_back(): void
    {
        $this->seedPersonalData();
        $participantCount = DB::table('participants')->count();

        $this->artisan('data:purge', ['--force' => true])->assertExitCode(0);
        $this->assertSame(0, DB::table('participants')->count());
        $this->assertNotEmpty(File::glob(storage_path('app/archives/*.json')));

        $this->artisan('data:restore', ['--force' => true])->assertExitCode(0);
        $this->assertSame($participantCount, DB::table('participants')->count());
        $this->assertGreaterThan(0, DB::table('verification_sessions')->count());

        foreach (File::glob(storage_path('app/archives/*.json')) as $f) {
            File::delete($f);
        }
    }

    /** @test */
    public function no_archive_flag_deletes_permanently(): void
    {
        $this->seedPersonalData();

        $this->artisan('data:purge', ['--force' => true, '--no-archive' => true])
            ->assertExitCode(0);

        $this->assertEmpty(File::glob(storage_path('app/archives/*.json')));

        $this->artisan('data:restore', ['--force' => true])->assertExitCode(0);
        $this->assertSame(0, DB::table('participants')->count());
    }

    /** @test */
    public function expired_archive_cannot_be_restored(): void
    {
        $this->seedPersonalData();
        $this->artisan('data:purge', ['--force' => true]);

        foreach (File::glob(storage_path('app/archives/*.json')) as $f) {
            touch($f, time() - (32 * 86400));
        }

        $this->artisan('data:restore', ['--force' => true])->assertExitCode(1);
        $this->assertSame(0, DB::table('participants')->count());

        foreach (File::glob(storage_path('app/archives/*.json')) as $f) {
            File::delete($f);
        }
    }

    /** @test */
    public function dry_run_reports_counts_without_deleting(): void
    {
        $this->seedPersonalData();

        $this->artisan('data:purge', ['--dry-run' => true])->assertExitCode(0);

        $this->assertGreaterThan(0, DB::table('participants')->count());
        $this->assertGreaterThan(0, DB::table('otps')->count());
    }

    /** @test */
    public function it_aborts_without_the_delete_confirmation(): void
    {
        $this->seedPersonalData();

        $this->artisan('data:purge')
            ->expectsQuestion('This permanently deletes ALL volunteer data. Type DELETE to confirm', 'no')
            ->assertExitCode(1);

        $this->assertGreaterThan(0, DB::table('participants')->count());
    }

    /** @test */
    public function it_refuses_to_run_in_production_even_with_force(): void
    {
        $this->seedPersonalData();
        config(['app.env' => 'production']);

        $this->artisan('data:purge', ['--force' => true])->assertExitCode(1);

        $this->assertGreaterThan(0, DB::table('participants')->count());
    }
}
