<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The identity of an outside company. A customer and a business
        // partner that point at the same row are the same business wearing
        // two hats. Rows are made lazily, when the two are first linked.
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('business_id')->nullable()->unique()->constrained()->nullOnDelete();
        });

        Schema::table('business_partners', function (Blueprint $table) {
            $table->foreignId('business_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('type', 30)->default('supplier');
        });

        // Existing partners: infer the type from what they already offer.
        DB::statement(<<<'SQL'
            UPDATE business_partners SET type = CASE
                WHEN EXISTS (SELECT 1 FROM service_business_partner s WHERE s.business_partner_id = business_partners.id)
                 AND EXISTS (SELECT 1 FROM material_business_partner m WHERE m.business_partner_id = business_partners.id) THEN 'both'
                WHEN EXISTS (SELECT 1 FROM service_business_partner s WHERE s.business_partner_id = business_partners.id) THEN 'service_provider'
                ELSE 'supplier'
            END
        SQL);
    }

    public function down(): void
    {
        Schema::table('business_partners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_id');
            $table->dropColumn('type');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_id');
        });

        Schema::dropIfExists('businesses');
    }
};
