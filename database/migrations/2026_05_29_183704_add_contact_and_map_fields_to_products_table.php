<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('poster_name')->nullable();
            $table->string('poster_email')->nullable();
            $table->text('poster_phones')->nullable();
            $table->string('company_name')->nullable();
            $table->string('lat')->nullable();
            $table->string('lng')->nullable();
            $table->text('address')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'poster_name',
                'poster_email',
                'poster_phones',
                'company_name',
                'lat',
                'lng',
                'address'
            ]);
        });
    }
};
