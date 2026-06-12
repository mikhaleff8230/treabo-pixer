<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class OptimizeVideoStorage extends Migration
{
    public function up()
    {
        if (Schema::hasTable('place_videos')) {
            Schema::table('place_videos', function (Blueprint $table) {
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

            try {
                Schema::table('place_videos', function (Blueprint $table) {
                    $table->index(['place_id', 'created_at'], 'place_videos_place_id_created_at_index');
                });
            } catch (\Exception $e) {
            }

            if (Schema::hasColumn('place_videos', 'duration')) {
                try {
                    Schema::table('place_videos', function (Blueprint $table) {
                        $table->index('duration', 'place_videos_duration_index');
                    });
                } catch (\Exception $e) {
                }
            }
        }

        if (Schema::hasTable('place_images')) {
            Schema::table('place_images', function (Blueprint $table) {
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

            try {
                Schema::table('place_images', function (Blueprint $table) {
                    $table->index(['place_id', 'created_at'], 'place_images_place_id_created_at_index');
                });
            } catch (\Exception $e) {
            }
        }
    }

    public function down()
    {
        if (Schema::hasTable('place_videos')) {
            Schema::table('place_videos', function (Blueprint $table) {
                try {
                    $table->dropIndex(['place_id', 'created_at']);
                } catch (\Exception $e) {
                }

                try {
                    $table->dropIndex('duration');
                } catch (\Exception $e) {
                }

                $table->dropColumn([
                    'preview_url', 'poster_url', 'thumbnail_url',
                    'duration', 'width', 'height', 'file_size', 'mime_type',
                ]);
            });
        }

        if (Schema::hasTable('place_images')) {
            Schema::table('place_images', function (Blueprint $table) {
                try {
                    $table->dropIndex(['place_id', 'created_at']);
                } catch (\Exception $e) {
                }

                $table->dropColumn([
                    'thumbnail_url', 'width', 'height', 'file_size', 'mime_type',
                ]);
            });
        }
    }
}
