<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE notifications
            ADD COLUMN dedupe_ref VARCHAR(191)
            GENERATED ALWAYS AS (COALESCE(
                JSON_UNQUOTE(JSON_EXTRACT(data, '$.application_id')),
                JSON_UNQUOTE(JSON_EXTRACT(data, '$.project_id'))
            )) VIRTUAL");

        DB::statement('ALTER TABLE notifications
            ADD UNIQUE INDEX notifications_user_type_ref_unique (user_id, type, dedupe_ref)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE notifications DROP INDEX notifications_user_type_ref_unique');
        DB::statement('ALTER TABLE notifications DROP COLUMN dedupe_ref');
    }
};
