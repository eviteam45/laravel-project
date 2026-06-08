<?php

use App\Models\IncentiveApplication;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalizes the owning contractor/customer onto applications so visibility
 * checks and dashboard sums are flat indexed WHEREs instead of correlated
 * whereHas('project', …) subqueries. Kept in sync by IncentiveApplication on
 * create and by Project when an owner is reassigned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incentive_applications', function (Blueprint $table) {
            $table->unsignedBigInteger('contractor_id')->nullable()->after('project_id');
            $table->unsignedBigInteger('customer_id')->nullable()->after('contractor_id');
            $table->index(['contractor_id', 'status']);
            $table->index(['customer_id', 'status']);
        });

        IncentiveApplication::query()
            ->with('project:id,contractor_id,customer_id')
            ->chunkById(200, function ($applications) {
                foreach ($applications as $application) {
                    $application->forceFill([
                        'contractor_id' => $application->project?->contractor_id,
                        'customer_id' => $application->project?->customer_id,
                    ])->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        Schema::table('incentive_applications', function (Blueprint $table) {
            $table->dropIndex(['contractor_id', 'status']);
            $table->dropIndex(['customer_id', 'status']);
            $table->dropColumn(['contractor_id', 'customer_id']);
        });
    }
};
