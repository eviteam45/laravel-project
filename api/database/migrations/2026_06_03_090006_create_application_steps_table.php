<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')
                ->constrained('incentive_applications')
                ->cascadeOnDelete();
            $table->string('step_key');
            $table->json('data')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'step_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_steps');
    }
};
