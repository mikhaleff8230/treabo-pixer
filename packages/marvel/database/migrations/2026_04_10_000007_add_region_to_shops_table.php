<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            if (!Schema::hasColumn('shops', 'region_id')) {
                $table->foreignId('region_id')->nullable()->constrained('regions');
                $table->index('region_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            if (Schema::hasColumn('shops', 'region_id')) {
                $table->dropForeign(['region_id']);
                $table->dropColumn('region_id');
            }
        });
    }
};
