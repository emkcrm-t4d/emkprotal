<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_threads', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->string('workspace_id', 50);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('restrict');
            $table->foreignId('lead_id')->nullable()->constrained('leads')->onDelete('set null');
            $table->foreignId('member_id')->nullable()->constrained('members')->onDelete('set null');
            $table->string('participant_email', 150);
            $table->string('subject', 255);
            $table->string('thread_reference', 255)->nullable()->index('idx_email_threads_reference');
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('message_count')->default(0);
            $table->boolean('is_resolved')->default(false);
            $table->timestamps();

            $table->index(['workspace_id', 'participant_email'], 'idx_email_threads_workspace_participant');
        });

        Schema::create('email_messages', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->string('workspace_id', 50);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('restrict');
            $table->foreignId('email_thread_id')->constrained('email_threads')->onDelete('cascade');
            $table->foreignId('lead_id')->nullable()->constrained('leads')->onDelete('set null');
            $table->foreignId('member_id')->nullable()->constrained('members')->onDelete('set null');
            $table->enum('direction', ['inbound', 'outbound']);
            $table->enum('status', ['received', 'queued', 'sent', 'failed'])->default('queued');
            $table->string('from_email', 150);
            $table->string('to_email', 150);
            $table->string('subject', 255);
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->string('message_id_header', 255)->nullable();
            $table->string('in_reply_to_header', 255)->nullable();
            $table->text('references_header')->nullable();
            $table->string('template_key', 100)->nullable();
            $table->string('milestone_type', 100)->nullable();
            $table->string('deduplication_key', 191)->nullable();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('failure_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'message_id_header'], 'uk_email_messages_workspace_msgid');
            $table->unique('deduplication_key', 'uk_email_messages_dedup_key');
            $table->index(['workspace_id', 'status'], 'idx_email_messages_workspace_status');
        });

        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('workspace_id', 50);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('restrict');
            $table->string('template_key', 100);
            $table->string('name', 150);
            $table->string('milestone_type', 100);
            $table->string('subject_en', 255);
            $table->longText('body_en');
            $table->string('subject_bn', 255)->nullable();
            $table->longText('body_bn')->nullable();
            $table->json('allowed_variables');
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['workspace_id', 'template_key'], 'uk_email_templates_workspace_key');
        });

        Schema::create('workflow_rules', function (Blueprint $table) {
            $table->id();
            $table->string('workspace_id', 50);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('restrict');
            $table->string('rule_key', 100);
            $table->string('name', 150);
            $table->string('trigger_event', 100);
            $table->foreignId('email_template_id')->nullable()->constrained('email_templates')->onDelete('set null');
            $table->integer('days_offset')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->json('conditions')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['workspace_id', 'rule_key'], 'uk_workflow_rules_workspace_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_rules');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('email_messages');
        Schema::dropIfExists('email_threads');
    }
};
