<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Feature::WeldRecords was renamed to Feature::QualificationRecords
     * (F7's generalization beyond welding) - that rename only changed the
     * PHP-side enum, not any plan_feature row already storing the old
     * string value. Plan::features() throws (Feature::from('weld-records')
     * has no matching case) the moment it tries to hydrate one, which is
     * exactly what broke the marketing page for any plan seeded before the
     * rename.
     */
    public function up(): void
    {
        DB::table('plan_feature')
            ->where('feature', 'weld-records')
            ->update(['feature' => 'qualification-records']);
    }

    public function down(): void
    {
        // Not reversed: a 'qualification-records' row created after this
        // migration ran didn't necessarily come from 'weld-records', so
        // blindly renaming back would mislabel it.
    }
};
