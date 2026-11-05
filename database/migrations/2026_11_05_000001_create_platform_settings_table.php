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
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100);
            $table->string('workspace_id', 50)->nullable();
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('cascade');
            $table->string('group', 50)->default('general');
            $table->longText('value')->nullable();
            $table->string('cast_type', 20)->default('string');
            $table->boolean('is_secret')->default(false);
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'key'], 'uq_settings_workspace_key');
            $table->index(['workspace_id', 'group'], 'idx_settings_workspace_group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
