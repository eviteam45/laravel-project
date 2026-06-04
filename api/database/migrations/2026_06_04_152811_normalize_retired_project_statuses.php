<?php

use App\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MAP = [
        'completed' => 'closed',
        'in_progress' => 'draft',
    ];

    public function up(): void
    {
        foreach (self::MAP as $retired => $valid) {
            DB::table('projects')->where('status', $retired)->update(['status' => $valid]);
        }

        DB::table('projects')
            ->whereNotIn('status', Project::STATUSES)
            ->update(['status' => 'draft']);
    }

    public function down(): void {}
};
