<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlaceHashtagTable extends Migration
{
    public function up()
    {
        Schema::create('place_hashtag', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('place_id');
            $table->unsignedBigInteger('hashtag_id');
            $table->timestamps();

            $table->foreign('place_id')->references('id')->on('places')->onDelete('cascade');
            $table->foreign('hashtag_id')->references('id')->on('hashtags')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('place_hashtag');
    }
} 