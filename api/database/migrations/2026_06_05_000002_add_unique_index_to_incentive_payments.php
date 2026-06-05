<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::table('incentive_payments', function (Blueprint $table) {
            $table->unique(['application_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('incentive_payments', function (Blueprint $table) {
            $table->dropUnique(['application_id', 'status']);
        });
    }
};
