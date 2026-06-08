<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FULLTEXT indexes so project/application search can use MATCH … AGAINST instead
 * of an unindexable leading-wildcard LIKE. MySQL-only; the scopes fall back to
 * LIKE on other engines.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('projects', fn (Blueprint $table) => $table->fullText(['name', 'address'], 'projects_search_fulltext'));
        Schema::table('contractors', fn (Blueprint $table) => $table->fullText(['company_name'], 'contractors_company_fulltext'));
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('projects', fn (Blueprint $table) => $table->dropFullText('projects_search_fulltext'));
        Schema::table('contractors', fn (Blueprint $table) => $table->dropFullText('contractors_company_fulltext'));
    }
};
