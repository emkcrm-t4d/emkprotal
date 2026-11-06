<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EMK-007, EMK-008, EMK-009, EMK-016: Add composite indexes for query optimization.
 *
 * Fixes identified in the Master Technical Audit to eliminate filesort operations
 * and full table scans on high-traffic CRM directory and audit log queries.
 */
return new class extends Migration
{
    public function up(): void
    {
        // EMK-007: Composite index on audit_logs (workspace_id, created_at)
        // Eliminates filesort on AuditLogViewer ORDER BY created_at DESC
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['workspace_id', 'created_at'], 'idx_audit_ws_created');
        });

        // EMK-008: Composite index on leads (workspace_id, submitted_at)
        // Optimizes date-range filtering on LeadDirectory
        Schema::table('leads', function (Blueprint $table) {
            $table->index(['workspace_id', 'submitted_at'], 'idx_leads_ws_submitted');
        });

        // EMK-009: Composite index on members (workspace_id, expiry_date)
        // Optimizes membership renewal and expiry milestone queries
        Schema::table('members', function (Blueprint $table) {
            $table->index(['workspace_id', 'expiry_date'], 'idx_members_ws_expiry');
        });

        // EMK-016: Composite indexes for phone and name search in directories
        // Prevents full table scans when searching by contact number or name
        if (Schema::hasColumn('leads', 'phone')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->index(['workspace_id', 'phone'], 'idx_leads_ws_phone');
            });
        }
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_ws_created');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('idx_leads_ws_submitted');
            if (Schema::hasIndex('leads', 'idx_leads_ws_phone')) {
                $table->dropIndex('idx_leads_ws_phone');
            }
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex('idx_members_ws_expiry');
        });
    }
};

