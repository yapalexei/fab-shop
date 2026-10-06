<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // An AI agent identity (see Shop::agents()), never a person: not
            // fillable, set only when the owner enables the MCP connection.
            $table->boolean('is_agent')->default(false);
        });

        // One row per tool call an AI client made over MCP. No arguments or
        // results - those hold customer data; this is for seeing what the
        // connected AI did and when, and whether it was refused.
        Schema::create('mcp_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tool', 80);
            $table->string('status', 20);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->timestamps();

            $table->index(['shop_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_requests');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_agent');
        });
    }
};
