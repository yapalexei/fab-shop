<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shop_feature_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('feature');
            $table->timestamp('started_at');
            // Null while active. Cancelling sets this rather than deleting
            // the row, so the shop-facing page can still show "you had this
            // until <date>" instead of the row just disappearing.
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'feature']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_feature_subscriptions');
    }
};
