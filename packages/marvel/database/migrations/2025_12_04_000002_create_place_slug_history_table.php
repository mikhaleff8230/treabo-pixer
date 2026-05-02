<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlaceSlugHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('place_slug_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('place_id');
            $table->string('old_slug')->index();
            $table->string('language')->default('ru');
            $table->timestamp('changed_at');
            $table->timestamps();

            // Foreign keys
            $table->foreign('place_id')->references('id')->on('places')->onDelete('cascade');
            
            // Индексы для быстрого поиска
            $table->index(['old_slug', 'language']);
            $table->index(['place_id', 'old_slug']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('place_slug_history');
    }
}

