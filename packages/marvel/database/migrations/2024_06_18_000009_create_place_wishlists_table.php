<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlaceWishlistsTable extends Migration
{
    public function up()
    {
        Schema::create('place_wishlists', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('place_id');
            $table->foreign('place_id')->references('id')->on('places')->onDelete('cascade');
            $table->timestamps();
            
            // Уникальный индекс для предотвращения дублирования
            $table->unique(['user_id', 'place_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('place_wishlists');
    }
}



