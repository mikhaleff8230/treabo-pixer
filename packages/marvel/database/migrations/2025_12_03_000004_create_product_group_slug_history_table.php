<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductGroupSlugHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_group_slug_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_group_id');
            $table->string('old_slug')->index();
            $table->string('language')->default('ru');
            $table->timestamp('changed_at');
            $table->timestamps();

            // Foreign keys
            $table->foreign('product_group_id')->references('id')->on('product_groups')->onDelete('cascade');
            
            // Индексы для быстрого поиска
            $table->index(['old_slug', 'language']);
            $table->index(['product_group_id', 'old_slug']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_group_slug_history');
    }
}

