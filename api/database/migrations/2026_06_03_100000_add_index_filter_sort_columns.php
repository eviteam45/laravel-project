<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index the date columns that the index endpoints filter (range) and sort on.
 * Status / FK / region columns are already indexed in their create migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->index('install_date');
        });

        Schema::table('incentive_applications', function (Blueprint $table) {
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['install_date']);
        });

        Schema::table('incentive_applications', function (Blueprint $table) {
            $table->dropIndex(['submitted_at']);
        });
    }
};
