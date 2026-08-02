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
        Schema::create('browser_releases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('version', 50);
            $table->string('platform', 20);
            $table->string('architecture', 20);
            $table->string('artifact_key', 500);
            $table->unsignedBigInteger('artifact_size');
            $table->string('sha256', 64);
            $table->text('signature')->nullable();
            $table->string('minimum_version', 50)->nullable();
            $table->boolean('mandatory')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('deprecated_at')->nullable();
            $table->timestamps();

            $table->unique(['version', 'platform', 'architecture']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('browser_releases');
    }
};
