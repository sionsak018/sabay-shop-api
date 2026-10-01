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
            $table->index('status');
            $table->index('category_id');
            $table->index('seller_id');
            $table->index('price');
            if (Schema::hasColumn('products', 'province_id')) {
                $table->index('province_id');
            }
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['category_id']);
            $table->dropIndex(['seller_id']);
            $table->dropIndex(['price']);
            if (Schema::hasColumn('products', 'province_id')) {
                $table->dropIndex(['province_id']);
            }
            $table->dropIndex(['created_at']);
        });
    }
};
