<?php

namespace App\Domain\Operations\Updates;

use App\Domain\Operations\Updates\Contracts\UpdateEnvironmentProbe;
use Illuminate\Support\Facades\DB;

final class NativeUpdateEnvironment implements UpdateEnvironmentProbe
{
    public function capture(): UpdateEnvironment
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            $rawVersion = (string) (DB::selectOne('SELECT sqlite_version() AS version')->version ?? '0.0.0');
            $family = 'sqlite';
        } else {
            $rawVersion = (string) (DB::selectOne('SELECT VERSION() AS version')->version ?? '0.0.0');
            $family = stripos($rawVersion, 'mariadb') !== false ? 'mariadb' : 'mysql';
        }
        preg_match('/\d+\.\d+\.\d+/', $rawVersion, $match);
        $free = @disk_free_space(base_path());

        return new UpdateEnvironment(
            PHP_VERSION,
            array_map('strtolower', get_loaded_extensions()),
            $family,
            $match[0] ?? '0.0.0',
            is_int($free) || is_float($free) ? (int) $free : 0,
        );
    }
}
