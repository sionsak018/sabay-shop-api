<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('slug');
        });
        Schema::table('brands', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('slug');
        });
        Schema::table('body_types', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('slug');
        });
    }

    public function down()
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
        Schema::table('body_types', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
    }
};
