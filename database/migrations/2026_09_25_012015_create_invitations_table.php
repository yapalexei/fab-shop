<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deliberately not tenant-scoped via BelongsToShop: an invitation is
     * looked up by its token by someone who, by definition, isn't a member
     * of the shop yet (and may be logged into a *different* shop already) -
     * the global scope would wrongly filter it out. The token itself is the
     * access boundary here, not the current-shop scope everything else uses.
     */
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            // Owner, Admin or Staff - checked against ShopPermissions::EDITABLE_ROLES
            // (plus Owner) at invite time; never a free-text role.
            $table->string('role');
            $table->string('token', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            // Re-inviting the same address refreshes one row rather than
            // piling up duplicates - only one invitation per email per shop.
            $table->unique(['shop_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
