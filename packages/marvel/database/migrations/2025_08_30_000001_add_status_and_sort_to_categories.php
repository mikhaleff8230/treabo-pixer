<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasColumn('categories', 'status')) {
                $table->enum('status', ['publish', 'draft'])->default('publish')->after('language');
            }
            if (!Schema::hasColumn('categories', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
            if (Schema::hasColumn('categories', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};




