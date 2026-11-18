<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Screenshot-only proof submissions (Phase 4: code entry is optional on
 * OpinionPadi's proof screen) have no completion code to reference. They
 * can never be approved — approval always re-validates a code — but they
 * must still land in the review queue so a reviewer can reject them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reward_submissions', function (Blueprint $table) {
            $table->foreignUuid('code_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('reward_submissions', function (Blueprint $table) {
            $table->foreignUuid('code_id')->nullable(false)->change();
        });
    }
};
