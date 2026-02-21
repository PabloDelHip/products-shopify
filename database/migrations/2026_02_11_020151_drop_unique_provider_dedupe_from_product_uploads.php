<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_uploads', function (Blueprint $table) {
            $table->dropUnique('uq_product_uploads_provider_dedupe');
        });
    }

    public function down(): void
    {
        Schema::table('product_uploads', function (Blueprint $table) {
            $table->unique(['provider', 'dedupe_key'], 'uq_product_uploads_provider_dedupe');
        });
    }
};
