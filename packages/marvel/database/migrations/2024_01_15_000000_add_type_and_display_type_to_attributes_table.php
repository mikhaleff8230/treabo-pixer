<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTypeAndDisplayTypeToAttributesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('attributes', function (Blueprint $table) {
            // Добавляем колонки только если их нет
            if (!Schema::hasColumn('attributes', 'type')) {
                $table->string('type')->nullable()->after('name');
            }
            if (!Schema::hasColumn('attributes', 'display_type')) {
                $table->string('display_type')->nullable()->after('type');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Удаляем колонки только если они существуют
        $columnsToDrop = [];
        if (Schema::hasColumn('attributes', 'type')) {
            $columnsToDrop[] = 'type';
        }
        if (Schema::hasColumn('attributes', 'display_type')) {
            $columnsToDrop[] = 'display_type';
        }
        
        if (!empty($columnsToDrop)) {
            Schema::table('attributes', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }
}


































