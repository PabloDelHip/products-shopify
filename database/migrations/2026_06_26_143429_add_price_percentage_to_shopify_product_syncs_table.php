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
        Schema::table('shopify_product_syncs', function (Blueprint $table) {
            $table->decimal('price_percentage', 8, 2)->nullable()->after('price_amount');
            $table->decimal('price_final', 12, 2)->nullable()->after('price_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('shopify_product_syncs', function (Blueprint $table) {
            $table->dropColumn(['price_percentage', 'price_final']);
        });
    }
};
