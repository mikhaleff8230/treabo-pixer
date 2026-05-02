<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddImageThumbnailFields extends Migration
{
    public function up()
    {
        Schema::table('place_images', function (Blueprint $table) {
            $table->string('thumbnail_url')->nullable()->after('url');
        });
    }

    public function down()
    {
        Schema::table('place_images', function (Blueprint $table) {
            $table->dropColumn('thumbnail_url');
        });
    }
} 