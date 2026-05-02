<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIpAddressToPlaceLikesTable extends Migration
{
    public function up()
    {
        // Удаляем внешний ключ перед изменением колонки
        Schema::table('place_likes', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        
        // Добавляем новые поля и делаем user_id nullable
        Schema::table('place_likes', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('user_id');
            $table->string('anonymous_id', 100)->nullable()->after('ip_address');
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
        
        // Добавляем внешний ключ обратно
        Schema::table('place_likes', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('place_likes', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['ip_address', 'anonymous_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
}

