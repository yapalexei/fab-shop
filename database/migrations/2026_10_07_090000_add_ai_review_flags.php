<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Set when the shop's AI connection creates or changes a quote or a
     * customer, cleared when a person marks it reviewed. A quote cannot be
     * sent while it - or its customer - is waiting for review: those fields
     * print on the quote and choose where it is emailed, so they are the one
     * way text an AI wrote reaches someone outside the shop.
     */
    public function up(): void
    {
        foreach (['quotes', 'customers'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->timestamp('ai_touched_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['quotes', 'customers'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('ai_touched_at');
            });
        }
    }
};
