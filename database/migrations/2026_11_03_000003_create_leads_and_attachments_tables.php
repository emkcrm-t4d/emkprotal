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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->string('workspace_id', 50)->nullable();
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('restrict');
            $table->string('target_space_code', 10);
            $table->enum('status', [
                'draft',
                'submitted',
                'under_review',
                'missing_items',
                'approved',
                'converted',
                'rejected',
                'blocked',
            ])->default('submitted');
            $table->char('bkash_txid', 10);
            $table->string('first_name', 50);
            $table->string('middle_name', 50)->nullable();
            $table->string('last_name', 50);
            $table->string('email', 150);
            $table->string('contact_number', 20);
            $table->date('date_of_birth');
            $table->unsignedInteger('age_at_submission');
            $table->enum('sex', ['male', 'female', 'na']);
            $table->string('address_house_no', 100);
            $table->string('address_road_no', 100)->nullable();
            $table->string('address_area', 100);
            $table->string('address_zip_code', 10);
            $table->string('address_division', 50);
            $table->string('address_district', 50);
            $table->string('website', 200)->nullable();
            $table->string('occupation', 50);
            $table->string('occupation_other', 100)->nullable();
            $table->string('institution_study', 150);
            $table->string('highest_certification', 50);
            $table->string('highest_certification_other', 100)->nullable();
            $table->string('area_of_subject', 150);
            $table->boolean('has_us_experience')->default(false);
            $table->string('us_experience_location', 150)->nullable();
            $table->boolean('is_american_alumni')->default(false);
            $table->string('american_institution_name', 150)->nullable();
            $table->string('employer_name', 150)->nullable();
            $table->string('job_title', 100)->nullable();
            $table->string('work_phone', 20)->nullable();
            $table->boolean('has_parental_consent')->default(false);
            $table->boolean('terms_agreed')->default(true);
            $table->enum('payment_verification_status', [
                'pending',
                'in_progress',
                'verified',
                'rejected',
            ])->default('pending');
            $table->enum('document_verification_status', [
                'pending',
                'in_progress',
                'verified',
                'missing_items',
                'rejected',
            ])->default('pending');
            $table->enum('photo_verification_status', [
                'pending',
                'in_progress',
                'verified',
                'reupload_requested',
                'rejected',
            ])->default('pending');
            $table->string('zoho_id', 64)->nullable()->unique();
            $table->unsignedBigInteger('converted_member_id')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->softDeletes();

            $table->unique('bkash_txid', 'uq_leads_bkash_txid');
            $table->unique(['email', 'workspace_id'], 'uq_leads_email_workspace');
            $table->index(['workspace_id', 'status'], 'idx_leads_workspace_status');
            $table->index('submitted_at', 'idx_leads_submitted_at');
            $table->index(
                ['payment_verification_status', 'document_verification_status', 'photo_verification_status'],
                'idx_leads_verification_streams'
            );
        });

        Schema::create('lead_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('workspace_id', 50)->nullable();
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('restrict');
            $table->foreignId('lead_id')->constrained('leads')->onDelete('cascade');
            $table->enum('document_type', ['identity_document', 'studio_photo', 'parental_consent']);
            $table->string('storage_path', 255);
            $table->string('original_filename', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('file_hash', 64);
            $table->boolean('youth_protection')->default(false);
            $table->timestamps();

            $table->index(['workspace_id', 'document_type'], 'idx_attachments_workspace_type');
            $table->index(['lead_id', 'document_type'], 'idx_attachments_lead_type');
        });

        Schema::create('lead_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->onDelete('cascade');
            $table->enum('stream', ['payment', 'document', 'photo']);
            $table->string('status', 30);
            $table->foreignId('verified_by_user_id')->constrained('users')->onDelete('restrict');
            $table->timestamp('verified_at');
            $table->json('evidence_payload');
            $table->string('rejection_reason_code', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'stream'], 'idx_verifications_lead_stream');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_verifications');
        Schema::dropIfExists('lead_attachments');
        Schema::dropIfExists('leads');
    }
};
