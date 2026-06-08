<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes for the dominant project list pattern: scope by owner,
 * filter by status, order by recency. The single-column FK indexes alone can't
 * serve the combined WHERE + ORDER BY.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->index(['contractor_id', 'status', 'created_at'], 'projects_contractor_status_created_index');
            $table->index(['customer_id', 'status', 'created_at'], 'projects_customer_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('projects_contractor_status_created_index');
            $table->dropIndex('projects_customer_status_created_index');
        });
    }
};
