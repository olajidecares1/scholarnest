<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->after('email');
        });

        $usedUsernames = [];

        DB::table('users')->orderBy('id')->select('id', 'email')->get()->each(function (object $user) use (&$usedUsernames) {
            $base = Str::slug(Str::before($user->email, '@'), '_') ?: 'user';
            $username = $base;
            $suffix = 1;

            while (in_array($username, $usedUsernames, true)) {
                $username = $base.$suffix;
                $suffix++;
            }

            $usedUsernames[] = $username;

            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
