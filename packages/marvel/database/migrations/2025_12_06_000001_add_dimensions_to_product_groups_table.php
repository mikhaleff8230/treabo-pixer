<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDimensionsToProductGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_groups', function (Blueprint $table) {
            $table->string('height')->nullable()->after('short_description');
            $table->string('length')->nullable()->after('height');
            $table->string('width')->nullable()->after('length');
            $table->decimal('weight', 10, 2)->nullable()->after('width');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_groups', function (Blueprint $table) {
            $table->dropColumn(['height', 'length', 'width', 'weight']);
        });
    }
}

