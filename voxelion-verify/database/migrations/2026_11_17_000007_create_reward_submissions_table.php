<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('task_id')->index();
            $table->foreignUuid('participant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('code_id')->references('id')->on('completion_codes')->cascadeOnDelete();
            $table->string('screenshot_s3_key')->nullable();
            $table->string('review_state')->default('pending')->index(); // pending | approved | rejected
            $table->string('reviewer_id')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamp('decision_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_submissions');
    }
};
