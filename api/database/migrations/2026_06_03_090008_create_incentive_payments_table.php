<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incentive_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')
                ->constrained('incentive_applications')
                ->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending');
            $table->date('scheduled_for')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incentive_payments');
    }
};
