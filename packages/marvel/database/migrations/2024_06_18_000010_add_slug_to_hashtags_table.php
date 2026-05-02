<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSlugToHashtagsTable extends Migration
{
    public function up()
    {
        Schema::table('hashtags', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            $table->index('slug');
        });
    }

    public function down()
    {
        Schema::table('hashtags', function (Blueprint $table) {
            $table->dropIndex(['slug']);
            $table->dropColumn('slug');
        });
    }
}



