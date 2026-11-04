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
        Schema::create('spaces', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->enum('category', ['Center', 'Corner']);
            $table->string('division', 50);
            $table->string('district', 50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('workspaces', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->foreignId('space_id')->constrained('spaces')->onDelete('restrict');
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workspaces');
        Schema::dropIfExists('spaces');
    }
};
