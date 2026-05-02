<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductSlugHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_slug_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('old_slug')->index();
            $table->string('language')->default('ru');
            $table->timestamp('changed_at');
            $table->timestamps();

            // Foreign keys
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            
            // Индексы для быстрого поиска
            $table->index(['old_slug', 'language']);
            $table->index(['product_id', 'old_slug']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_slug_history');
    }
}

