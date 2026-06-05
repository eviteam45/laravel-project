<?php

use App\Models\IncentiveApplication;
use App\Models\IncentivePayment;
use App\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $checks = [
        'projects' => ['projects_status_check', Project::STATUSES],
        'incentive_applications' => ['incentive_applications_status_check', IncentiveApplication::STATUSES],
        'incentive_payments' => ['incentive_payments_status_check', IncentivePayment::STATUSES],
    ];

    public function up(): void
    {
        foreach ($this->checks as $table => [$name, $statuses]) {
            $list = "'".implode("','", $statuses)."'";
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK (status IN ({$list}))");
        }
    }

    public function down(): void
    {
        foreach ($this->checks as $table => [$name]) {
            DB::statement("ALTER TABLE {$table} DROP CHECK {$name}");
        }
    }
};
