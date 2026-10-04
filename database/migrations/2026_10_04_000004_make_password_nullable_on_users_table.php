<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Google customers sign in with Google only. Until now they still carried a
     * random 40-character hash nobody can know, which made "this account has no
     * password" impossible to express. Nullable storage makes it explicit.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });

        DB::table('users')
            ->whereNotNull('google_id')
            ->whereNotNull('password')
            ->update(['password' => null]);
    }

    public function down(): void
    {
        DB::table('users')
            ->whereNotNull('google_id')
            ->whereNull('password')
            ->update(['password' => Hash::make(Str::random(40))]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};