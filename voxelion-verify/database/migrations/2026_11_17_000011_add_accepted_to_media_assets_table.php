<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Marks whether the asset passed its quality gate. Rejected captures
        // are preserved (accepted=false) only as evidence for manual_review
        // sessions — they get the same signed-URL + deletion treatment as
        // accepted media.
        Schema::table('media_assets', function (Blueprint $table) {
            $table->boolean('accepted')->default(true)->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            $table->dropColumn('accepted');
        });
    }
};
