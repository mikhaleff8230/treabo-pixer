<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSlugNumericCodeToProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Добавляем поле slug_numeric_code в таблицу products
        Schema::table('products', function (Blueprint $table) {
            $table->string('slug_numeric_code', 12)->nullable()->after('slug');
            $table->index('slug_numeric_code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['slug_numeric_code']);
            $table->dropColumn('slug_numeric_code');
        });
    }
}

