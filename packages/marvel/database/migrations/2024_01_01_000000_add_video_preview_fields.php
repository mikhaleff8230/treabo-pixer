<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVideoPreviewFields extends Migration
{
    public function up()
    {
        // Добавляем preview_url если не существует
        if (!Schema::hasColumn('place_videos', 'preview_url')) {
            Schema::table('place_videos', function (Blueprint $table) {
                $table->string('preview_url')->nullable()->after('url');
            });
        }
        
        // Добавляем poster_url если не существует
        if (!Schema::hasColumn('place_videos', 'poster_url')) {
            Schema::table('place_videos', function (Blueprint $table) {
                $table->string('poster_url')->nullable()->after('preview_url');
            });
        }
        
        // Проверяем существование колонки перед удалением
        if (Schema::hasColumn('place_videos', 'thumbnail_url')) {
            Schema::table('place_videos', function (Blueprint $table) {
                $table->dropColumn('thumbnail_url');
            });
        }
    }

    public function down()
    {
        // Удаляем preview_url если существует
        if (Schema::hasColumn('place_videos', 'preview_url')) {
            Schema::table('place_videos', function (Blueprint $table) {
                $table->dropColumn('preview_url');
            });
        }
        
        // Удаляем poster_url если существует
        if (Schema::hasColumn('place_videos', 'poster_url')) {
            Schema::table('place_videos', function (Blueprint $table) {
                $table->dropColumn('poster_url');
            });
        }
        
        // Добавляем обратно thumbnail_url если его не было
        if (!Schema::hasColumn('place_videos', 'thumbnail_url')) {
            Schema::table('place_videos', function (Blueprint $table) {
                $table->string('thumbnail_url')->nullable()->after('url');
            });
        }
    }
} 