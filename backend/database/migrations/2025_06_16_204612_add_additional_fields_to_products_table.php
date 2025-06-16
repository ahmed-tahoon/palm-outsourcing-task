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
        Schema::table('products', function (Blueprint $table) {
            $table->string('url')->nullable()->after('image_url');
            $table->string('source')->nullable()->after('url');
            $table->text('description')->nullable()->after('source');

            // Add index for URL for faster lookups when checking duplicates
            $table->index('url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['url']);
            $table->dropColumn(['url', 'source', 'description']);
        });
    }
};
