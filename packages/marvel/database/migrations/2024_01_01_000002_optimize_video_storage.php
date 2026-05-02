<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class OptimizeVideoStorage extends Migration
{
    public function up()
    {
        Schema::table('place_videos', function (Blueprint $table) {
            // Добавляем поля для оптимизации только если их нет
            if (!Schema::hasColumn('place_videos', 'preview_url')) {
                $table->string('preview_url')->nullable()->after('url');
            }
            if (!Schema::hasColumn('place_videos', 'poster_url')) {
                $table->string('poster_url')->nullable()->after('preview_url');
            }
            if (!Schema::hasColumn('place_videos', 'thumbnail_url')) {
                $table->string('thumbnail_url')->nullable()->after('poster_url');
            }
            if (!Schema::hasColumn('place_videos', 'duration')) {
                $table->decimal('duration', 8, 2)->nullable()->after('thumbnail_url');
            }
            if (!Schema::hasColumn('place_videos', 'width')) {
                $table->integer('width')->nullable()->after('duration');
            }
            if (!Schema::hasColumn('place_videos', 'height')) {
                $table->integer('height')->nullable()->after('width');
            }
            if (!Schema::hasColumn('place_videos', 'file_size')) {
                $table->bigInteger('file_size')->nullable()->after('height');
            }
            if (!Schema::hasColumn('place_videos', 'mime_type')) {
                $table->string('mime_type')->nullable()->after('file_size');
            }
        });
        
        // Добавляем индексы (если уже существуют, будет ошибка, но это не критично)
        try {
            Schema::table('place_videos', function (Blueprint $table) {
                $table->index(['place_id', 'created_at'], 'place_videos_place_id_created_at_index');
            });
        } catch (\Exception $e) {
            // Индекс уже существует, игнорируем
        }
        
        if (Schema::hasColumn('place_videos', 'duration')) {
            try {
                Schema::table('place_videos', function (Blueprint $table) {
                    $table->index('duration', 'place_videos_duration_index');
                });
            } catch (\Exception $e) {
                // Индекс уже существует, игнорируем
            }
        }
        
        Schema::table('place_images', function (Blueprint $table) {
            // Добавляем поля для оптимизации изображений только если их нет
            if (!Schema::hasColumn('place_images', 'thumbnail_url')) {
                $table->string('thumbnail_url')->nullable()->after('url');
            }
            if (!Schema::hasColumn('place_images', 'width')) {
                $table->integer('width')->nullable()->after('thumbnail_url');
            }
            if (!Schema::hasColumn('place_images', 'height')) {
                $table->integer('height')->nullable()->after('width');
            }
            if (!Schema::hasColumn('place_images', 'file_size')) {
                $table->bigInteger('file_size')->nullable()->after('height');
            }
            if (!Schema::hasColumn('place_images', 'mime_type')) {
                $table->string('mime_type')->nullable()->after('file_size');
            }
        });
        
        // Добавляем индекс для place_images
        try {
            Schema::table('place_images', function (Blueprint $table) {
                $table->index(['place_id', 'created_at'], 'place_images_place_id_created_at_index');
            });
        } catch (\Exception $e) {
            // Индекс уже существует, игнорируем
        }
    }

    public function down()
    {
        Schema::table('place_videos', function (Blueprint $table) {
            $table->dropIndex(['place_id', 'created_at']);
            $table->dropIndex('duration');
            $table->dropColumn([
                'preview_url', 'poster_url', 'thumbnail_url', 
                'duration', 'width', 'height', 'file_size', 'mime_type'
            ]);
        });
        
        Schema::table('place_images', function (Blueprint $table) {
            $table->dropIndex(['place_id', 'created_at']);
            $table->dropColumn([
                'thumbnail_url', 'width', 'height', 'file_size', 'mime_type'
            ]);
        });
    }
} 