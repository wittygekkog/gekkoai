<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedTinyInteger('admin_type')->default(1);
            $table->unsignedTinyInteger('status')->default(0);
            $table->string('access_scope', 20)->default('platform');
            $table->boolean('must_change_password')->default(true);
            $table->text('mfa_secret')->nullable();
            $table->boolean('mfa_enabled')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('setting_slug')->unique();
            $table->string('setting_name');
            $table->text('setting_value')->nullable();
            $table->string('setting_group', 50)->default('general');
            $table->string('input_type', 30)->default('text');
            $table->string('help_text')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tenants', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('website_url')->nullable();
            $table->string('timezone', 64)->default('Asia/Manila');
            $table->string('locale', 12)->default('en');
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('tenant_users', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained('admins')->restrictOnDelete();
            $table->string('role', 40);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id'], 'tenant_users_tenant_user_unique');
        });

        Schema::create('tenant_connectors', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained('tenants')->restrictOnDelete();
            $table->string('base_url')->nullable();
            $table->string('mode', 20)->default('simulation');
            $table->string('connector_version', 20)->default('1.0.0');
            $table->string('authentication_type', 40)->default('hmac');
            $table->text('encrypted_credentials')->nullable();
            $table->string('key_id')->nullable();
            $table->string('status', 30)->default('simulation');
            $table->timestamp('last_health_check_at')->nullable();
            $table->text('last_health_message')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_settings', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('setting_key');
            $table->text('setting_value')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'setting_key'], 'tenant_settings_key_unique');
        });

        Schema::create('tenant_operations', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('operation_name', 120);
            $table->string('version', 20)->default('1.0');
            $table->text('description')->nullable();
            $table->json('input_schema');
            $table->json('output_schema');
            $table->string('risk_level', 20);
            $table->boolean('enabled')->default(true);
            $table->boolean('approval_required')->default(true);
            $table->boolean('read_only')->default(false);
            $table->json('required_fields');
            $table->timestamps();
            $table->unique(['tenant_id', 'operation_name'], 'tenant_operations_name_unique');
        });

        Schema::create('ai_conversations', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained('admins')->restrictOnDelete();
            $table->string('title');
            $table->string('status', 30)->default('open');
            $table->string('model_provider', 80)->nullable();
            $table->string('model_name', 120)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'updated_at'], 'ai_conversations_tenant_updated_idx');
        });

        Schema::create('ai_messages', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('ai_conversations')->restrictOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('role', 30);
            $table->string('message_type', 40);
            $table->text('content');
            $table->json('structured_content')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'created_at'], 'ai_messages_conversation_created_idx');
        });

        Schema::create('ai_attachments', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('message_id')->nullable()->constrained('ai_messages')->nullOnDelete();
            $table->string('file_path');
            $table->string('mime_type', 120);
            $table->unsignedInteger('file_size');
            $table->timestamp('retention_until')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_transcriptions', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('attachment_id')->constrained('ai_attachments')->restrictOnDelete();
            $table->text('transcript')->nullable();
            $table->string('language', 20)->nullable();
            $table->string('model_name', 120)->nullable();
            $table->json('transcription_metadata')->nullable();
            $table->text('corrected_transcript')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_operation_requests', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('conversation_id')->nullable()->constrained('ai_conversations')->restrictOnDelete();
            $table->foreignUuid('initiated_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('original_instruction')->nullable();
            $table->string('intent', 120)->nullable();
            $table->json('parameters')->nullable();
            $table->json('missing_fields')->nullable();
            $table->json('ambiguities')->nullable();
            $table->decimal('confidence_score', 8, 4)->nullable();
            $table->json('confidence_metadata')->nullable();
            $table->string('calibration_version', 40)->nullable();
            $table->string('risk_level', 20)->nullable();
            $table->string('status', 40);
            $table->string('execution_mode', 30)->nullable();
            $table->string('analyzer', 40)->nullable();
            $table->char('parameter_hash', 64)->nullable();
            $table->string('idempotency_key', 191)->nullable();
            $table->uuid('correlation_id');
            $table->timestamps();
            $table->index(['tenant_id', 'status'], 'ai_operations_tenant_status_idx');
            $table->index('correlation_id', 'ai_operations_correlation_idx');
            $table->unique('idempotency_key', 'ai_operations_idempotency_unique');
        });

        Schema::create('ai_operation_steps', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->foreignUuid('operation_request_id')->constrained('ai_operation_requests')->restrictOnDelete();
            $table->unsignedSmallInteger('step_number');
            $table->string('operation_name', 120);
            $table->json('parameters')->nullable();
            $table->string('status', 40);
            $table->json('result')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_operation_executions', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->foreignUuid('operation_request_id')->constrained('ai_operation_requests')->restrictOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('connector_id')->nullable()->constrained('tenant_connectors')->nullOnDelete();
            $table->uuid('external_operation_id')->nullable();
            $table->char('request_fingerprint', 64)->nullable();
            $table->string('status', 40);
            $table->json('response_data')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_operation_state_history', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->foreignUuid('operation_request_id')->constrained('ai_operation_requests')->restrictOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->string('reason')->nullable();
            $table->foreignUuid('changed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index('operation_request_id', 'ai_state_history_request_idx');
        });

        Schema::create('ai_clarifications', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('conversation_id')->constrained('ai_conversations')->restrictOnDelete();
            $table->foreignUuid('operation_request_id')->constrained('ai_operation_requests')->restrictOnDelete();
            $table->text('question');
            $table->json('expected_fields');
            $table->text('response')->nullable();
            $table->unsignedTinyInteger('round_number');
            $table->string('status', 20)->default('open');
            $table->timestamps();
        });

        Schema::create('ai_approvals', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('operation_request_id')->constrained('ai_operation_requests')->restrictOnDelete();
            $table->foreignUuid('requested_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignUuid('assigned_to')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->json('approval_snapshot');
            $table->char('parameter_hash', 64);
            $table->foreignUuid('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('decision_reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_escalations', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->foreignUuid('operation_request_id')->nullable()->constrained('ai_operation_requests')->nullOnDelete();
            $table->foreignUuid('assigned_to')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('reason', 80);
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('open');
            $table->text('resolution')->nullable();
            $table->foreignUuid('resolved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->json('context')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_knowledge_documents', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('title');
            $table->string('source_type', 40);
            $table->string('source_reference')->nullable();
            $table->longText('content');
            $table->unsignedInteger('version')->default(1);
            $table->string('approval_status', 20)->default('pending');
            $table->timestamps();
            $table->index(['tenant_id', 'approval_status'], 'ai_knowledge_tenant_status_idx');
        });

        Schema::create('ai_knowledge_chunks', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('document_id')->constrained('ai_knowledge_documents')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->text('content');
            $table->json('embedding')->nullable();
            $table->string('embedding_reference')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('ai_feedback', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('operation_request_id')->nullable()->constrained('ai_operation_requests')->nullOnDelete();
            $table->foreignUuid('submitted_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('issue_type', 40)->nullable();
            $table->text('correction')->nullable();
            $table->string('review_status', 20)->default('pending');
            $table->timestamps();
        });

        Schema::create('ai_learning_examples', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('source_feedback_id')->nullable()->constrained('ai_feedback')->nullOnDelete();
            $table->text('input_text');
            $table->string('expected_intent', 120);
            $table->json('expected_parameters')->nullable();
            $table->string('expected_action', 120)->nullable();
            $table->string('approval_status', 20)->default('pending');
            $table->string('dataset_version', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('ai_model_versions', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->string('provider', 80);
            $table->string('model_name', 120);
            $table->string('purpose', 40);
            $table->string('version', 40);
            $table->text('configuration')->nullable();
            $table->string('deployment_status', 30)->default('inactive');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('ai_evaluation_runs', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->foreignUuid('model_version_id')->nullable()->constrained('ai_model_versions')->nullOnDelete();
            $table->string('dataset_version', 40);
            $table->json('metrics');
            $table->boolean('passed')->default(false);
            $table->timestamp('evaluated_at')->useCurrent();
        });

        Schema::create('ai_audit_logs', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('event_type', 80);
            $table->string('resource_type', 80)->nullable();
            $table->string('resource_id', 64)->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'created_at'], 'ai_audit_tenant_created_idx');
            $table->index('correlation_id', 'ai_audit_correlation_idx');
        });

        Schema::create('ai_system_errors', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('service', 80);
            $table->string('error_code', 80);
            $table->text('message');
            $table->uuid('correlation_id')->nullable();
            $table->string('severity', 20)->default('error');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('sim_bookings', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->uuid('reference')->unique();
            $table->string('idempotency_key', 191)->unique();
            $table->string('guest_name', 120);
            $table->string('email', 190);
            $table->string('mobile', 40);
            $table->date('arrival');
            $table->date('departure');
            $table->unsignedTinyInteger('guests');
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('calculated_cents')->default(0);
            $table->unsignedInteger('total_cents')->default(0);
            $table->unsignedInteger('deposit_cents')->default(0);
            $table->unsignedTinyInteger('deposit_percent')->default(30);
            $table->unsignedSmallInteger('rate_index')->default(100);
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'arrival'], 'sim_bookings_tenant_arrival_idx');
        });

        Schema::create('sim_reserved_nights', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('booking_id')->constrained('sim_bookings')->restrictOnDelete();
            $table->date('night');
            $table->unique(['tenant_id', 'night'], 'sim_reserved_nights_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sim_reserved_nights');
        Schema::dropIfExists('sim_bookings');
        Schema::dropIfExists('ai_system_errors');
        Schema::dropIfExists('ai_audit_logs');
        Schema::dropIfExists('ai_evaluation_runs');
        Schema::dropIfExists('ai_model_versions');
        Schema::dropIfExists('ai_learning_examples');
        Schema::dropIfExists('ai_feedback');
        Schema::dropIfExists('ai_knowledge_chunks');
        Schema::dropIfExists('ai_knowledge_documents');
        Schema::dropIfExists('ai_escalations');
        Schema::dropIfExists('ai_approvals');
        Schema::dropIfExists('ai_clarifications');
        Schema::dropIfExists('ai_operation_state_history');
        Schema::dropIfExists('ai_operation_executions');
        Schema::dropIfExists('ai_operation_steps');
        Schema::dropIfExists('ai_operation_requests');
        Schema::dropIfExists('ai_transcriptions');
        Schema::dropIfExists('ai_attachments');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('tenant_operations');
        Schema::dropIfExists('tenant_settings');
        Schema::dropIfExists('tenant_connectors');
        Schema::dropIfExists('tenant_users');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('admins');
    }
};
