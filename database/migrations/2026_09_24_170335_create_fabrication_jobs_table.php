<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Named `fabrication_jobs`, not `jobs` - that table already belongs to
     * Laravel's database queue driver.
     */
    public function up(): void
    {
        Schema::create('fabrication_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('job_number');
            $table->string('status')->default('not_started');
            $table->timestamps();

            $table->unique(['shop_id', 'job_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fabrication_jobs');
    }
};
