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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->string('member_id', 30)->unique();
            $table->string('workspace_id', 50);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('restrict');
            $table->foreignId('lead_id')->unique()->constrained('leads')->onDelete('restrict');
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->onDelete('restrict');
            $table->foreignId('membership_term_id')->constrained('membership_terms')->onDelete('restrict');
            $table->enum('status', [
                'active',
                'inactive',
                'restricted',
                'revoked',
                'archived_reversed',
            ])->default('active');
            $table->string('first_name', 50);
            $table->string('middle_name', 50)->nullable();
            $table->string('last_name', 50);
            $table->string('email', 150)->unique();
            $table->string('contact_number', 20);
            $table->date('date_of_birth');
            $table->enum('sex', ['male', 'female', 'na']);
            $table->string('address_house_no', 100);
            $table->string('address_road_no', 100)->nullable();
            $table->string('address_area', 100);
            $table->string('address_zip_code', 10);
            $table->string('address_division', 50);
            $table->string('address_district', 50);
            $table->string('occupation', 50);
            $table->string('institution_study', 150);
            $table->string('highest_certification', 50);
            $table->string('area_of_subject', 150);
            $table->date('start_date');
            $table->date('expiry_date');
            $table->string('zoho_id', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['workspace_id', 'status'], 'idx_members_workspace_status');
            $table->index('expiry_date', 'idx_members_expiry');
            $table->index('email', 'idx_members_email');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique();
            $table->string('workspace_id', 50);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('restrict');
            $table->foreignId('member_id')->constrained('members')->onDelete('restrict');
            $table->foreignId('lead_id')->nullable()->constrained('leads')->onDelete('set null');
            $table->decimal('amount_bdt', 10, 2);
            $table->string('payment_method', 30)->default('bkash_manual');
            $table->string('payment_reference', 50);
            $table->enum('status', [
                'paid',
                'unpaid',
                'voided',
                'void_reversed',
            ])->default('paid');
            $table->timestamp('paid_at');
            $table->string('zoho_id', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['workspace_id', 'status'], 'idx_invoices_workspace_status');
            $table->index('payment_reference', 'idx_invoices_payment_ref');
        });

        Schema::create('id_cards', function (Blueprint $table) {
            $table->id();
            $table->string('workspace_id', 50);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('restrict');
            $table->foreignId('member_id')->constrained('members')->onDelete('restrict');
            $table->string('card_number', 50)->unique();
            $table->enum('status', [
                'provided_for_printing',
                'card_printed_ready_for_pickup',
                'delivered',
                'superseded_replaced',
                'voided_reversal',
            ])->default('provided_for_printing');
            $table->string('batch_reference', 50)->nullable();
            $table->string('replacement_reason', 100)->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status'], 'idx_cards_workspace_status');
        });

        Schema::create('member_change_requests', function (Blueprint $table) {
            $table->id();
            $table->string('workspace_id', 50);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('restrict');
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->enum('request_type', ['general_update', 'email_change', 'renewal']);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->json('old_payload');
            $table->json('new_payload');
            $table->text('justification')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('manager_notes')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status'], 'idx_requests_workspace_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_change_requests');
        Schema::dropIfExists('id_cards');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('members');
    }
};
