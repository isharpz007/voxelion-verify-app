<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->string('phone_code_hash')->nullable()->after('phone');
            $table->timestamp('phone_code_expires_at')->nullable()->after('phone_code_hash');
            $table->timestamp('phone_verified_at')->nullable()->after('phone_code_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn(['phone_code_hash', 'phone_code_expires_at', 'phone_verified_at']);
        });
    }
};
