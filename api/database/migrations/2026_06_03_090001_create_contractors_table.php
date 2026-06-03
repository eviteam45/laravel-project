<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('license_no')->nullable();
            $table->string('phone')->nullable();
            $table->string('region')->nullable();
            $table->string('status')->default('active'); // active | inactive | pending
            $table->timestamps();

            $table->index('region');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractors');
    }
};
