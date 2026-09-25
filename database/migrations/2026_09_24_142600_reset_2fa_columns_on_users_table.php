<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop TOTP columns — moving to email-based 2FA
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ]);
        });

        // Replace the misleading "two_factor_devices" name
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('two_factor_devices');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('trusted_devices')->nullable()->after('password');
            $table->boolean('email_two_factor_enabled')
                ->default(true)
                ->after('trusted_devices');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['trusted_devices', 'email_two_factor_enabled']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->json('two_factor_devices')->nullable();
        });
    }
};
