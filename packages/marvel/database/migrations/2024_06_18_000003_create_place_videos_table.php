<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlaceVideosTable extends Migration
{
    public function up()
    {
        Schema::create('place_videos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('place_id');
            $table->string('url');
            $table->timestamps();

            $table->foreign('place_id')->references('id')->on('places')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('place_videos');
    }
} 