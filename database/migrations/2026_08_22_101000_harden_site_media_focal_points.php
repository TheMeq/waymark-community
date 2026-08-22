<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER site_media_focal_insert BEFORE INSERT ON site_media FOR EACH ROW WHEN NEW.focal_point_x < 0 OR NEW.focal_point_x > 1 OR NEW.focal_point_y < 0 OR NEW.focal_point_y > 1 BEGIN SELECT RAISE(ABORT, 'site media focal point outside image'); END;");
            DB::unprepared("CREATE TRIGGER site_media_focal_update BEFORE UPDATE OF focal_point_x, focal_point_y ON site_media FOR EACH ROW WHEN NEW.focal_point_x < 0 OR NEW.focal_point_x > 1 OR NEW.focal_point_y < 0 OR NEW.focal_point_y > 1 BEGIN SELECT RAISE(ABORT, 'site media focal point outside image'); END;");

            return;
        }
        DB::statement('ALTER TABLE site_media ADD CONSTRAINT site_media_focal_points_check CHECK (focal_point_x >= 0 AND focal_point_x <= 1 AND focal_point_y >= 0 AND focal_point_y <= 1)');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS site_media_focal_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS site_media_focal_update');

            return;
        }
        DB::statement('ALTER TABLE site_media DROP CONSTRAINT site_media_focal_points_check');
    }
};
