<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('participant_id')->constrained()->cascadeOnDelete();
            $table->string('consent_version');
            $table->timestamp('checkbox_1_at')->nullable();
            $table->timestamp('checkbox_2_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->string('disclosure_hash', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
