<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smtp_relays', function (Blueprint $table) {
            $table->id();
            $table->string('workspace_id', 50)->nullable();
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('cascade');
            $table->string('name', 120);
            $table->integer('priority')->default(1);
            $table->string('sender_email', 190);
            $table->string('sender_name', 120);
            $table->string('host', 190);
            $table->unsignedSmallInteger('port')->default(587);
            $table->string('encryption', 20)->default('starttls');
            $table->string('username', 190);
            $table->text('password');
            $table->unsignedInteger('throttle_seconds')->default(0);
            $table->string('reply_to', 190)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status', 30)->default('untested');
            $table->timestamp('last_tested_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'is_active', 'priority'], 'idx_smtp_ws_active_priority');
            $table->index(['workspace_id', 'status'], 'idx_smtp_ws_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smtp_relays');
    }
};
