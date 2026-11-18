<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->string('email_code_hash')->nullable()->after('email_verified_at');
            $table->timestamp('email_code_expires_at')->nullable()->after('email_code_hash');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropColumn(['email_verified_at', 'email_code_hash', 'email_code_expires_at']);
        });
    }
};
