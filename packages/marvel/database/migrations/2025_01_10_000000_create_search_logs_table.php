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
        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->string('query', 500);
            $table->string('type', 50)->nullable()->comment('products, places, categories, shops');
            $table->integer('results_count')->default(0);
            
            // Click tracking
            $table->unsignedBigInteger('result_id')->nullable();
            $table->string('result_type', 50)->nullable();
            $table->integer('position')->nullable()->comment('Position in search results');
            
            // User tracking
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            
            // Session tracking
            $table->string('session_id', 100)->nullable();
            
            // Metadata
            $table->json('metadata')->nullable()->comment('Additional search parameters');
            
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes for analytics queries
            $table->index('query');
            $table->index('type');
            $table->index('user_id');
            $table->index('created_at');
            $table->index(['result_id', 'result_type']);
        });

        // Create index for full-text search on query field (optional)
        DB::statement('CREATE FULLTEXT INDEX search_logs_query_fulltext ON search_logs(query)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_logs');
    }
};


