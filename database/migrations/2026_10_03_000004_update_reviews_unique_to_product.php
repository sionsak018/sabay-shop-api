<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            // Add the product-scoped unique first so it can serve the
            // reviewer_id foreign key before the old unique is dropped.
            $table->unique(['reviewer_id', 'product_id']);
            $table->dropUnique(['reviewer_id', 'seller_id']);
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->unique(['reviewer_id', 'seller_id']);
            $table->dropUnique(['reviewer_id', 'product_id']);
        });
    }
};
