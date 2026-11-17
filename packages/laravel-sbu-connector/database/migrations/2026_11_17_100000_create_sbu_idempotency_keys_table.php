<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sbu_idempotency_keys', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('idempotency_key', 191);
            $table->string('operation_name', 120);
            $table->char('request_fingerprint', 64);
            $table->string('status', 32);
            $table->uuid('operation_id')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key'], 'sbu_idempotency_tenant_key_unique');
            $table->index('operation_id', 'sbu_idempotency_operation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sbu_idempotency_keys');
    }
};
