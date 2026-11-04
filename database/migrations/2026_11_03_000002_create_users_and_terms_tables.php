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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('workspace_id', 50)->nullable();
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('restrict');
            $table->string('name', 150);
            $table->string('email', 150)->unique();
            $table->string('password', 255)->nullable();
            $table->string('guard', 20)->default('web');
            $table->boolean('is_active')->default(true);
            $table->boolean('must_reset_password')->default(false);
            $table->string('zoho_id', 64)->nullable()->unique();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['workspace_id', 'guard'], 'idx_users_workspace_guard');
            $table->index('email', 'idx_users_email');
        });

        Schema::create('membership_terms', function (Blueprint $table) {
            $table->id();
            $table->string('workspace_id', 50);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('cascade');
            $table->string('name', 100);
            $table->string('code', 50);
            $table->unsignedInteger('duration_months');
            $table->decimal('fee_bdt', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['workspace_id', 'code'], 'uq_terms_workspace_code');
            $table->index(['workspace_id', 'is_active'], 'idx_terms_workspace_active');
        });

        Schema::create('member_id_sequences', function (Blueprint $table) {
            $table->string('space_code', 10);
            $table->unsignedInteger('year');
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->primary(['space_code', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_id_sequences');
        Schema::dropIfExists('membership_terms');
        Schema::dropIfExists('users');
    }
};
