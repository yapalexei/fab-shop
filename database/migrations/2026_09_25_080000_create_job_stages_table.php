<?php

use App\Models\Shop;
use App\Support\JobStages;
use App\Support\ShopPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->unsignedInteger('position');
            // Not Started/Shipped/Invoiced (see App\Enums\JobStageRole); null
            // for a shop's own custom stages in between.
            $table->string('role')->nullable();
            $table->timestamps();

            // No unique constraint on position - App\Support\JobStagePositions
            // is the one place position gets assigned (a full renumber on
            // every create/reorder/delete), so app-level consistency is
            // enough and a two-phase shuffle to satisfy a DB constraint
            // mid-reorder isn't worth the complexity.
            $table->index(['shop_id', 'position']);
        });

        // Case-insensitive uniqueness per shop, same pattern as material_types.
        DB::statement('CREATE UNIQUE INDEX job_stages_shop_id_lower_name_unique ON job_stages (shop_id, lower(name))');

        Shop::query()->each(function (Shop $shop) {
            JobStages::seedDefaults($shop);
            ShopPermissions::seedRoles($shop);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('job_stages');
    }
};
