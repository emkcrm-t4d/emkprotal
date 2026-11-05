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
        Schema::create('zoho_import_batches', function (Blueprint $table) {
            $table->id();
            $table->char('batch_uuid', 36)->unique();
            $table->string('workspace_id', 50);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('restrict');
            $table->enum('source_type', ['contacts_members', 'leads', 'form_submissions']);
            $table->string('source_filename', 255);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('successful_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->json('error_summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status'], 'idx_zoho_batches_workspace_status');
        });

        Schema::create('zoho_staging_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('zoho_import_batches')->onDelete('cascade');
            $table->string('workspace_id', 50);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('restrict');
            $table->string('zoho_id', 64)->nullable();
            $table->string('source_type', 30);
            $table->json('raw_payload');
            $table->json('mapped_payload')->nullable();
            $table->enum('validation_status', ['valid', 'warning', 'invalid'])->default('valid');
            $table->json('validation_errors')->nullable();
            $table->string('imported_entity_type', 50)->nullable();
            $table->unsignedBigInteger('imported_entity_id')->nullable();
            $table->enum('status', ['staged', 'imported', 'skipped', 'failed'])->default('staged');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('zoho_id', 'idx_zoho_staging_zoho_id');
            $table->index(['batch_id', 'status'], 'idx_zoho_staging_batch_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zoho_staging_records');
        Schema::dropIfExists('zoho_import_batches');
    }
};
