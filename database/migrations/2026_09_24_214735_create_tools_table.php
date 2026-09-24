<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Which materials the tool is rated to cut (App\Enums\CuttableMaterial
            // values) - a list, since plenty of tooling handles several.
            $table->jsonb('cuts');
            $table->decimal('cost', 10, 2);
            $table->string('unit')->default('each');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tools');
    }
};
