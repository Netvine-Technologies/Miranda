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
            $table->foreignId('canonical_product_id')->nullable()->after('store_id')->constrained('canonical_products')->nullOnDelete();
            $table->unsignedBigInteger('shopify_product_id')->nullable()->after('product_type');
            $table->string('currency', 8)->nullable()->after('shopify_product_id');
            $table->string('product_url')->nullable()->after('currency');
            $table->string('image_url')->nullable()->after('product_url');
            $table->longText('description')->nullable()->after('image_url');
            $table->json('tags')->nullable()->after('description');

            $table->index('canonical_product_id');
            $table->index('shopify_product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['canonical_product_id']);
            $table->dropIndex(['shopify_product_id']);
            $table->dropConstrainedForeignId('canonical_product_id');
            $table->dropColumn([
                'shopify_product_id',
                'currency',
                'product_url',
                'image_url',
                'description',
                'tags',
            ]);
        });
    }
};
