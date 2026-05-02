<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'starts_at')) {
                $table->timestamp('starts_at')->nullable()->after('product_id');
            }
            if (!Schema::hasColumn('subscriptions', 'status')) {
                $table->string('status', 32)->default('active')->after('expires_at');
            }
        });

        if (Schema::hasTable('subscriptions') && Schema::hasColumn('subscriptions', 'starts_at')) {
            DB::table('subscriptions')->whereNull('starts_at')->update([
                'starts_at' => DB::raw('COALESCE(created_at, updated_at, NOW())'),
            ]);
        }
        if (Schema::hasTable('subscriptions') && Schema::hasColumn('subscriptions', 'status')) {
            DB::table('subscriptions')->whereNull('status')->update(['status' => 'active']);
        }

        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'billing_access_type')) {
                $table->string('billing_access_type', 32)->nullable()->after('subscription_days');
            }
            if (!Schema::hasColumn('products', 'duration_days')) {
                $table->unsignedInteger('duration_days')->nullable()->after('billing_access_type');
            }
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('required_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('title');
            $table->string('content_type', 32)->default('video');
            $table->text('content_url')->nullable();
            $table->longText('content_body')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('drip_days')->default(0);
            $table->timestamps();

            $table->index(['course_id', 'position']);
        });

        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedTinyInteger('progress_percent')->nullable();
            $table->timestamp('last_watched_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_progress');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('courses');

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'duration_days')) {
                $table->dropColumn('duration_days');
            }
            if (Schema::hasColumn('products', 'billing_access_type')) {
                $table->dropColumn('billing_access_type');
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('subscriptions', 'starts_at')) {
                $table->dropColumn('starts_at');
            }
        });
    }
};
