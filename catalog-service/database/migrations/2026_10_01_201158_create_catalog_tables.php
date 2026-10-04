<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Сторы (Тенанты)
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        // 2. Дерево категорий (Adjacency List)
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->integer('position')->default(0);
            $table->timestamps();

            // В рамках одного магазина slug категории уникален
            $table->unique(['store_id', 'slug']);
            $table->index(['store_id', 'parent_id', 'position']);
        });

        // 3. Товары
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('sku');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->jsonb('attributes')->default('{}');
            $table->timestamps();

            // SKU уникален в пределах одного стора
            $table->unique(['store_id', 'sku']);
            $table->unique(['store_id', 'slug']);
            $table->index(['store_id', 'category_id', 'is_active']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX products_attributes_gin_idx ON products USING gin (attributes);');
        }

        // 4. Изображения товаров (MinIO)
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('path'); // Путь к объекту в бакете MinIO (напр. products/1/uuid.webp)
            $table->string('url');  // Публичный URL
            $table->boolean('is_primary')->default(false);
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'is_primary']);
        });

        // 5. Источник истины по остаткам (Stocks)
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity')->default(0);          // Физический остаток
            $table->integer('reserved_quantity')->default(0); // Заблокировано под заказы
            $table->timestamps();

            $table->unique(['store_id', 'product_id']);
            $table->index(['store_id', 'quantity']);
        });

        // 6. Журнал резерваций (Сага и защита от дублирования сообщений Kafka)
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid('order_id');
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity');
            $table->string('status')->default('reserved'); // reserved, confirmed, released
            $table->timestamps();

            // Идемпотентность: один товар в заказе резервируется строго один раз
            $table->unique(['order_id', 'product_id']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('stocks');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('stores');
    }
};