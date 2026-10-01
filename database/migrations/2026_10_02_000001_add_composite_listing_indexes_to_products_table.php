<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Composite indexes covering the actual listing query shapes in
     * ProductController::index, each of which was running a filesort:
     *
     *   where('status', 'active')->latest()                  -> (status, created_at)
     *   + where('category_id', ...)                          -> (status, category_id, created_at)
     *   + where('district_id', ...)                          -> (status, district_id, created_at)
     *   + orderBy('price', ...)                              -> (status, price)
     *
     * `status` and `created_at` each already have standalone indexes, but
     * MySQL can only use one index per table, so filtering on status while
     * ordering by created_at forces either a filesort or a scan of the
     * created_at index with the status predicate applied per row.
     *
     * district_id, brand_id, brand_model_id and body_type_id are already
     * indexed by their foreign key constraints, so they are not repeated here.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $indexes = [
                ['status', 'created_at'],
                ['status', 'category_id', 'created_at'],
                ['status', 'district_id', 'created_at'],
                ['status', 'price'],
            ];

            foreach ($indexes as $columns) {
                $name = 'products_'.implode('_', $columns).'_index';

                if (! Schema::hasIndex('products', $name)) {
                    $table->index($columns, $name);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach ([
                'products_status_created_at_index',
                'products_status_category_id_created_at_index',
                'products_status_district_id_created_at_index',
                'products_status_price_index',
            ] as $name) {
                $table->dropIndex($name);
            }
        });
    }
};
