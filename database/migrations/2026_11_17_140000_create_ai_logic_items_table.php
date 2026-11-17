<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rules and instructions the Logic screen serves for each business unit.
 * These guide how an instruction is read. They do not grant tools.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_logic_items', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('slug');
            $table->string('title');
            $table->text('body');
            $table->string('intent', 120)->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'kind', 'slug'], 'ai_logic_items_slug_unique');
            $table->index(['tenant_id', 'kind', 'status'], 'ai_logic_items_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_logic_items');
    }
};
