<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('workspace_id', 50)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_guard', 20)->default('web');
            $table->string('event_type', 100);
            $table->string('auditable_type', 100)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['workspace_id', 'event_type'], 'idx_audit_workspace_event');
            $table->index(['auditable_type', 'auditable_id'], 'idx_audit_target');
            $table->index('actor_id', 'idx_audit_actor');
            $table->index('created_at', 'idx_audit_created_at');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::unprepared("
                CREATE TRIGGER trg_audit_logs_prevent_update
                BEFORE UPDATE ON audit_logs
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Audit logs are strictly append-only and cannot be modified or updated (DEC-11).';
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER trg_audit_logs_prevent_delete
                BEFORE DELETE ON audit_logs
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Audit logs are strictly append-only and cannot be deleted (DEC-11).';
                END;
            ");
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared("
                CREATE TRIGGER trg_audit_logs_prevent_update
                BEFORE UPDATE ON audit_logs
                FOR EACH ROW
                BEGIN
                    SELECT RAISE(ABORT, 'SQLSTATE[45000]: Audit logs are strictly append-only and cannot be modified or updated (DEC-11).');
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER trg_audit_logs_prevent_delete
                BEFORE DELETE ON audit_logs
                FOR EACH ROW
                BEGIN
                    SELECT RAISE(ABORT, 'SQLSTATE[45000]: Audit logs are strictly append-only and cannot be deleted (DEC-11).');
                END;
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'sqlite'])) {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_audit_logs_prevent_update;');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_audit_logs_prevent_delete;');
        }

        Schema::dropIfExists('audit_logs');
    }
};
