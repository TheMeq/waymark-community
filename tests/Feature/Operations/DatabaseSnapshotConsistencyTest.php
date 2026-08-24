<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Backups\PortableDatabaseExporter;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class DatabaseSnapshotConsistencyTest extends TestCase
{
    private string $exportPath;

    private ?string $sqlitePath = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exportPath = storage_path('framework/testing/database-snapshot-'.bin2hex(random_bytes(8)).'.jsonl');

        if (DB::getDriverName() === 'sqlite') {
            $this->sqlitePath = storage_path('framework/testing/database-snapshot-'.bin2hex(random_bytes(8)).'.sqlite');
            touch($this->sqlitePath);
            config()->set('database.connections.snapshot_test', [
                'driver' => 'sqlite',
                'database' => $this->sqlitePath,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
            DB::setDefaultConnection('snapshot_test');
            DB::purge('snapshot_test');
            DB::statement('PRAGMA journal_mode=WAL');
        }

        config()->set('database.connections.snapshot_mutator', config('database.connections.'.DB::getDefaultConnection()));
        DB::purge('snapshot_mutator');

        Schema::create('snapshot_a_parents', function ($table): void {
            $table->id();
            $table->string('version');
        });
        Schema::create('snapshot_z_children', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id');
            $table->string('version');
        });
        DB::table('snapshot_a_parents')->insert(['id' => 1, 'version' => 'before']);
        DB::table('snapshot_z_children')->insert(['id' => 1, 'parent_id' => 1, 'version' => 'before']);
    }

    protected function tearDown(): void
    {
        Event::forget(QueryExecuted::class);
        Schema::dropIfExists('snapshot_z_children');
        Schema::dropIfExists('snapshot_a_parents');
        @unlink($this->exportPath);
        if (is_string($this->sqlitePath)) {
            DB::disconnect('snapshot_mutator');
            DB::disconnect('snapshot_test');
            @unlink($this->sqlitePath);
            @unlink($this->sqlitePath.'-wal');
            @unlink($this->sqlitePath.'-shm');
        }
        parent::tearDown();
    }

    public function test_related_rows_are_exported_from_one_logical_snapshot_during_concurrent_mutation(): void
    {
        $mutated = false;
        Event::listen(QueryExecuted::class, function (QueryExecuted $query) use (&$mutated): void {
            if ($mutated || ! preg_match('/from [`"]?snapshot_a_parents[`"]?/i', $query->sql)) {
                return;
            }
            $mutated = true;
            DB::connection('snapshot_mutator')->table('snapshot_z_children')->insert([
                'id' => 2,
                'parent_id' => 1,
                'version' => 'after',
            ]);
        });

        app(PortableDatabaseExporter::class)->export($this->exportPath, 10);

        $contents = file_get_contents($this->exportPath);
        $this->assertTrue($mutated, 'The controlled concurrent mutation did not run.');
        $this->assertStringContainsString('"version":"before"', $contents);
        $this->assertStringNotContainsString('"version":"after"', $contents);
    }
}
