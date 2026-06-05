<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE incentive_applications
            ADD COLUMN active_project_id BIGINT UNSIGNED
            GENERATED ALWAYS AS (IF(deleted_at IS NULL, project_id, NULL)) VIRTUAL');

        DB::statement('ALTER TABLE incentive_applications
            ADD UNIQUE INDEX incentive_applications_active_project_unique (active_project_id)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE incentive_applications DROP INDEX incentive_applications_active_project_unique');
        DB::statement('ALTER TABLE incentive_applications DROP COLUMN active_project_id');
    }
};
