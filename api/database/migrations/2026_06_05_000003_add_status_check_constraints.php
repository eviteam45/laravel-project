<?php

use App\Enums\ApplicationStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $checks = [
        'projects' => ['projects_status_check', ProjectStatus::class],
        'incentive_applications' => ['incentive_applications_status_check', ApplicationStatus::class],
        'incentive_payments' => ['incentive_payments_status_check', PaymentStatus::class],
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        foreach ($this->checks as $table => [$name, $enum]) {
            $list = "'".implode("','", $enum::values())."'";
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK (status IN ({$list}))");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            return;
        }

        $drop = $driver === 'mysql' ? 'DROP CHECK' : 'DROP CONSTRAINT';
        foreach ($this->checks as $table => [$name]) {
            DB::statement("ALTER TABLE {$table} {$drop} {$name}");
        }
    }
};
