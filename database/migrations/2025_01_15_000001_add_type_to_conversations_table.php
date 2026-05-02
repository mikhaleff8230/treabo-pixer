<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('conversations', 'type')) {
                $table->enum('type', ['private', 'group'])->default('private')->after('id');
            }
            if (!Schema::hasColumn('conversations', 'title')) {
                $table->string('title')->nullable()->after('type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasColumn('conversations', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('conversations', 'title')) {
                $table->dropColumn('title');
            }
        });
    }
};









