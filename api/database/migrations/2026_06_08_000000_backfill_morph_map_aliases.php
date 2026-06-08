<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rewrites existing polymorphic *_type columns from fully-qualified class names
 * to the enforced morph-map aliases (see AppServiceProvider::MORPH_MAP). Without
 * this, requireMorphMap() would throw when resolving any pre-existing row.
 */
return new class extends Migration
{
    /**
     * @var array<string, string> table => polymorphic *_type column
     */
    private array $columns = [
        'personal_access_tokens' => 'tokenable_type',
        'audit_logs' => 'subject_type',
        'documents' => 'documentable_type',
        'notes' => 'notable_type',
    ];

    public function up(): void
    {
        $toAlias = array_flip(Relation::morphMap());

        foreach ($this->columns as $table => $column) {
            foreach ($toAlias as $class => $alias) {
                DB::table($table)->where($column, $class)->update([$column => $alias]);
            }
        }
    }

    public function down(): void
    {
        $toClass = Relation::morphMap();

        foreach ($this->columns as $table => $column) {
            foreach ($toClass as $alias => $class) {
                DB::table($table)->where($column, $alias)->update([$column => $class]);
            }
        }
    }
};
