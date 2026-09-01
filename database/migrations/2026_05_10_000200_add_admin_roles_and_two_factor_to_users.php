<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('staff')->after('email');
            }

            if (! Schema::hasColumn('users', 'app_authentication_secret')) {
                $table->text('app_authentication_secret')->nullable()->after('password');
            }

            if (! Schema::hasColumn('users', 'app_authentication_recovery_codes')) {
                $table->text('app_authentication_recovery_codes')->nullable()->after('app_authentication_secret');
            }
        });

        $adminEmail = env('ADMIN_EMAIL', 'admin@bedsheets.ptree.lk');

        $updated = DB::table('users')
            ->where('email', $adminEmail)
            ->update(['role' => 'super_admin']);

        if ($updated === 0) {
            $firstUserId = DB::table('users')->orderBy('id')->value('id');

            if ($firstUserId) {
                DB::table('users')->where('id', $firstUserId)->update(['role' => 'super_admin']);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'app_authentication_recovery_codes')) {
                $table->dropColumn('app_authentication_recovery_codes');
            }

            if (Schema::hasColumn('users', 'app_authentication_secret')) {
                $table->dropColumn('app_authentication_secret');
            }

            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};
