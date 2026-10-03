<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Plain nullable column (no FK constraint) so the migration works
            // identically on MySQL and SQLite, which the test suite uses.
            $table->unsignedBigInteger('reply_to_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('reply_to_id');
        });
    }
};
