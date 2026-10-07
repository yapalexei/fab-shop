<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two-factor requirement policy (platform default + per-shop override),
     * the shop-deletion grace period, and the table that tracks data exports.
     *
     * A shop's `two_factor_required_roles` is null while it follows the
     * platform default; an empty list means "this shop requires nobody".
     * `*_policy_set_at` is when the roles or grace period last changed - it
     * starts every affected person's countdown.
     */
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->json('two_factor_required_roles')->nullable();
            $table->unsignedSmallInteger('two_factor_grace_days')->default(14);
            $table->timestamp('two_factor_policy_set_at')->nullable();
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->json('two_factor_required_roles')->nullable();
            $table->unsignedSmallInteger('two_factor_grace_days')->nullable();
            $table->timestamp('two_factor_policy_set_at')->nullable();
            $table->timestamp('deletion_requested_at')->nullable();
            $table->timestamp('deletion_scheduled_for')->nullable()->index();
        });

        Schema::create('data_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('pending'); // pending | ready | failed
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_exports');

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['two_factor_required_roles', 'two_factor_grace_days', 'two_factor_policy_set_at', 'deletion_requested_at', 'deletion_scheduled_for']);
        });

        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn(['two_factor_required_roles', 'two_factor_grace_days', 'two_factor_policy_set_at']);
        });
    }
};
