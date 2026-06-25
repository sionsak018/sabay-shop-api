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
            $table->index('price');
            $table->index('condition');
            $table->index('created_at');
            $table->index(['category_id', 'status']); // Compound index for category filtering
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['price']);
            $table->dropIndex(['condition']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['category_id', 'status']);
        });
    }
};
