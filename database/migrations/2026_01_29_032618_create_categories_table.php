<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            // Origen / proveedor
            $table->string('provider'); // syscom, ingram, manual, etc
            $table->string('provider_category_id')->nullable();

            // Shopify mapping (opcional)
            $table->string('shopify_id')->nullable();
            $table->string('shopify_type')->nullable(); // collection | tag | product_type

            $table->string('parent_provider_category_id')->nullable();

            // Datos de la categoría
            $table->string('name');
            $table->unsignedTinyInteger('level'); // 1,2,3...
            $table->boolean('active')->default(true);

            $table->timestamps();

            // Índices útiles
            $table->index(['provider', 'provider_category_id']);
            $table->index(['shopify_type', 'shopify_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
