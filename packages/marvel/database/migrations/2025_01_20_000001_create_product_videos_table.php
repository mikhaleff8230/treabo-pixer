<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductVideosTable extends Migration
{
    public function up()
    {
        Schema::create('product_videos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('url');
            $table->string('preview_url')->nullable();
            $table->string('poster_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->decimal('duration', 8, 2)->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index('product_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_videos');
    }
}










