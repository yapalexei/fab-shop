<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional TOTP two-factor sign-in. The secret is encrypted by the model
     * cast; recovery codes are stored only as hashes, so a database leak
     * can't be replayed. `two_factor_confirmed_at` stays null until the user
     * proves their authenticator app works - a half-finished setup never
     * locks anyone out. `two_factor_last_step` is the last accepted 30-second
     * window, so one code can't be used twice.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->unsignedBigInteger('two_factor_last_step')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_step']);
        });
    }
};
