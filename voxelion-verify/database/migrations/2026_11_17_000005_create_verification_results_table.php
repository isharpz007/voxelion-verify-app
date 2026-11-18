<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->references('id')->on('verification_sessions')->cascadeOnDelete();
            $table->string('challenge')->nullable();
            $table->json('liveness_response_json')->nullable();
            $table->decimal('similarity_score', 5, 4)->nullable();
            $table->string('outcome'); // verified | failed | manual_review
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_results');
    }
};
