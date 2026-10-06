<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The date the customer needs the work by - carried onto the job when a
     * won quote is converted. A plain calendar date, like the job's own.
     */
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->date('due_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });
    }
};
