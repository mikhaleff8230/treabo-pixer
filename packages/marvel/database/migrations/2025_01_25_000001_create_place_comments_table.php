<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlaceCommentsTable extends Migration
{
    public function up()
    {
        Schema::create('place_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('place_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('parent_id')->nullable(); // Для ответов на комментарии
            $table->text('comment');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('place_id')->references('id')->on('places')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('place_comments')->onDelete('cascade');
            
            $table->index('place_id');
            $table->index('user_id');
            $table->index('parent_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('place_comments');
    }
}

