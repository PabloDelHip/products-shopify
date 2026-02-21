<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_product_sync_category', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shopify_product_sync_id')
                ->constrained('shopify_product_syncs')
                ->cascadeOnDelete();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();

            // evita duplicados
            $table->unique(
                ['shopify_product_sync_id', 'category_id'],
                'sync_category_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_product_sync_category');
    }
};
