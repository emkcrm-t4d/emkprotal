<?php

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
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->string('workspace_id', 50)->nullable();
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('restrict');
            $table->string('slug', 100)->unique();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->json('schema');
            $table->json('settings')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('is_default')->default(false);
            $table->string('version', 20)->default('1.0');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'is_published'], 'idx_forms_workspace_published');
            $table->index(['slug', 'is_published'], 'idx_forms_slug_published');
        });

        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('form_id')->constrained('forms')->onDelete('cascade');
            $table->string('workspace_id', 50)->nullable();
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('restrict');
            $table->foreignId('lead_id')->nullable()->constrained('leads')->onDelete('set null');
            $table->json('payload');
            $table->enum('status', ['submitted', 'processed', 'failed'])->default('submitted');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['form_id', 'created_at'], 'idx_submissions_form_created');
            $table->index(['workspace_id', 'status'], 'idx_submissions_workspace_status');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropUnique('uq_leads_email_workspace');
            $table->index(['email', 'workspace_id'], 'idx_leads_email_workspace');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('idx_leads_email_workspace');
            $table->unique(['email', 'workspace_id'], 'uq_leads_email_workspace');
        });

        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('forms');
    }
};
