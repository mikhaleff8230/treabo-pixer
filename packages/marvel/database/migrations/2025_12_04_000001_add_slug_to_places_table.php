<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddSlugToPlacesTable extends Migration
{
    public function up()
    {
        Schema::table('places', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('title');
            $table->string('language')->default('ru')->after('description');
            $table->index('slug');
        });

        // Генерируем slug для существующих записей
        $places = DB::table('places')->get();
        foreach ($places as $place) {
            $slug = \Illuminate\Support\Str::slug($place->title);
            $originalSlug = $slug;
            $counter = 1;
            
            // Проверяем уникальность slug
            while (DB::table('places')->where('slug', $slug)->where('id', '!=', $place->id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
            
            DB::table('places')->where('id', $place->id)->update(['slug' => $slug]);
        }

        // Делаем slug обязательным после заполнения существующих записей
        Schema::table('places', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
        });

        // Устанавливаем начальное значение auto_increment для длинных ID (для новых записей)
        // Это сделает ID похожими на: 1000000000001, 1000000000002, и т.д.
        $maxId = DB::table('places')->max('id') ?? 0;
        $newAutoIncrement = max(1000000000000, $maxId + 1);
        DB::statement("ALTER TABLE places AUTO_INCREMENT = {$newAutoIncrement}");
    }

    public function down()
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropIndex(['slug']);
            $table->dropColumn(['slug', 'language']);
        });
        
        // Возвращаем auto_increment к нормальному значению
        $maxId = DB::table('places')->max('id') ?? 0;
        DB::statement("ALTER TABLE places AUTO_INCREMENT = " . ($maxId + 1));
    }
}

