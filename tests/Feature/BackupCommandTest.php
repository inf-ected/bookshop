<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Config\Config;
use Tests\TestCase;

class BackupCommandTest extends TestCase
{
    public function test_backup_run_only_db_exits_successfully(): void
    {
        // Create a real SQLite file so the dumper has a valid path to read
        $dbPath = storage_path('app/test-backup-db.sqlite');
        touch($dbPath);

        // Create a fake sqlite3 script — must output SQL to stdout so the dump file
        // is non-empty (spatie/db-dumper fails on an empty dump file)
        $fakeBinDir = storage_path('app/fake-bin');
        @mkdir($fakeBinDir, 0o755, true);
        $fakeSqlite3 = $fakeBinDir.'/sqlite3';
        file_put_contents($fakeSqlite3, "#!/bin/sh\necho 'BEGIN IMMEDIATE;'\necho 'COMMIT;'\nexit 0\n");
        chmod($fakeSqlite3, 0o755);

        // Override config so the backup uses our temp SQLite file and a local disk
        config([
            'database.connections.sqlite.database' => $dbPath,
            'database.connections.sqlite.dump' => [
                'dumpBinaryPath' => $fakeBinDir.'/',
            ],
            'backup.backup.source.databases' => ['sqlite'],
            'backup.backup.destination.disks' => ['local'],
            'backup.backup.destination.path' => 'test-backups',
        ]);

        // Force the scoped Config singleton to be rebuilt with the updated config
        $this->app->forgetScopedInstances();
        $this->app->forgetInstance(Config::class);

        Storage::fake('local');

        $this->artisan('backup:run --only-db --disable-notifications')
            ->assertExitCode(0);

        @unlink($dbPath);
        @unlink($fakeSqlite3);
        @rmdir($fakeBinDir);
    }
}
