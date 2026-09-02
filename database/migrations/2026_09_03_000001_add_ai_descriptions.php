<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->longText('description')->nullable()->after('seo_description');
            $table->timestamp('description_generated_at')->nullable()->after('specs');
            $table->string('ai_model', 80)->nullable()->after('description_generated_at');
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->timestamp('description_generated_at')->nullable()->after('description');
            $table->string('ai_model', 80)->nullable()->after('description_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['description', 'description_generated_at', 'ai_model']);
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn(['description_generated_at', 'ai_model']);
        });
    }
};
