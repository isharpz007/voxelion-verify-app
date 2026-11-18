<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn([
                'email_code_hash', 'email_code_expires_at',
                'phone_code_hash', 'phone_code_expires_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->string('email_code_hash')->nullable();
            $table->timestamp('email_code_expires_at')->nullable();
            $table->string('phone_code_hash')->nullable();
            $table->timestamp('phone_code_expires_at')->nullable();
        });
    }
};
