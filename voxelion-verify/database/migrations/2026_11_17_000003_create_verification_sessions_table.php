<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('participant_id')->constrained()->cascadeOnDelete();
            $table->string('state')->index();
            $table->string('handoff_jti')->unique();
            $table->string('current_challenge')->nullable();
            $table->unsignedTinyInteger('photo_attempts')->default(0);
            $table->unsignedTinyInteger('liveness_attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_sessions');
    }
};
