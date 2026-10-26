<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Stock;
use App\Models\Store;
use App\Models\StoreLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        Model::withoutEvents(function (): void {
            foreach ($this->stores() as $storeData) {
                $store = Store::query()->updateOrCreate(
                    ['slug' => $storeData['slug']],
                    [
                        'name' => $storeData['name'],
                        'is_active' => true,
                        'city' => $storeData['city'],
                        'address' => $storeData['address'],
                        'phone' => $storeData['phone'],
                        'working_hours' => $storeData['working_hours'],
                    ],
                );

                $locations = $this->seedLocations($store, $storeData['locations']);
                $categories = [];
                $this->seedTree($store, $storeData['categories'], null, $categories);

                foreach ($storeData['products'] as $item) {
                    $this->seedProduct($store, $locations, $categories[$item[2]], $item);
                }
            }
        });

        $this->command?->info(sprintf(
            'Каталог: магазинов %d, категорий %d, товаров %d.',
            Store::query()->count(),
            Category::query()->count(),
            Product::query()->count(),
        ));
    }

    /**
     * @param  list<array{slug: string, name: string, children?: list<array<string, mixed>>}>  $nodes
     * @param  array<string, Category>  $index
     */
    private function seedTree(Store $store, array $nodes, ?int $parentId, array &$index): void
    {
        foreach ($nodes as $position => $node) {
            $category = Category::query()->updateOrCreate(
                ['store_id' => $store->id, 'slug' => $node['slug']],
                [
                    'parent_id' => $parentId,
                    'name' => $node['name'],
                    'position' => $position + 1,
                ],
            );

            $index[$category->slug] = $category;
            $this->seedTree($store, $node['children'] ?? [], $category->id, $index);
        }
    }

    /**
     * @param  list<array{name: string, city: string, address: string, phone: string, working_hours: string}>  $locations
     * @return list<StoreLocation>
     */
    private function seedLocations(Store $store, array $locations): array
    {
        $saved = [];

        foreach ($locations as $location) {
            $saved[] = StoreLocation::query()->updateOrCreate(
                ['store_id' => $store->id, 'address' => $location['address']],
                [
                    'name' => $location['name'],
                    'city' => $location['city'],
                    'phone' => $location['phone'],
                    'working_hours' => $location['working_hours'],
                    'is_active' => true,
                ],
            );
        }

        return $saved;
    }

    /**
     * @param  list<StoreLocation>  $locations
     * @param  array{0: string, 1: string, 2: string, 3: float|int, 4: int, 5: array<string, mixed>}  $item
     */
    private function seedProduct(Store $store, array $locations, Category $category, array $item): void
    {
        [$sku, $name, , $price, $stock, $attributes] = $item;

        $product = Product::query()->firstOrCreate(
            ['store_id' => $store->id, 'sku' => $sku],
            [
                'category_id' => $category->id,
                'name' => $name,
                'slug' => Str::slug($sku),
                'description' => "{$name}. Тестовый товар для витрины и поиска.",
                'price' => $price,
                'is_active' => true,
                'attributes' => $attributes,
            ],
        );

        foreach ($locations as $index => $location) {
            $quantity = $index === 0 ? (int) $stock : (int) max(0, intdiv((int) $stock, 3));

            Stock::query()->updateOrCreate(
                ['location_id' => $location->id, 'product_id' => $product->id],
                [
                    'store_id' => $store->id,
                    'quantity' => $quantity,
                    'reserved_quantity' => 0,
                ],
            );
        }

        ProductImage::query()->firstOrCreate(
            ['product_id' => $product->id, 'position' => 0],
            [
                'path' => "seed/{$sku}.jpg",
                'url' => 'https://picsum.photos/seed/'.rawurlencode($sku).'/800/800',
                'is_primary' => true,
            ],
        );
    }

    /**
     * @return list<array{slug: string, name: string, city: string, address: string, phone: string, working_hours: string, categories: list<array<string, mixed>>, products: list<array{0: string, 1: string, 2: string, 3: float|int, 4: int, 5: array<string, mixed>}>}>
     */
    private function stores(): array
    {
        return [
            [
                'slug' => 'tech-universe',
                'name' => 'ТехноВселенная',
                'city' => 'Минск',
                'address' => 'ул. Немига, 5',
                'phone' => '+375 29 111-11-11',
                'working_hours' => '10:00 - 22:00',
                'locations' => [
                    ['name' => 'Немига', 'city' => 'Минск', 'address' => 'ул. Немига, 5', 'phone' => '+375 29 111-11-11', 'working_hours' => '10:00 - 22:00'],
                    ['name' => 'Каменная Горка', 'city' => 'Минск', 'address' => 'ул. Притыцкого, 156', 'phone' => '+375 29 111-22-22', 'working_hours' => '10:00 - 21:00'],
                ],
                'categories' => [
                    ['slug' => 'electronics', 'name' => 'Электроника', 'children' => [
                        ['slug' => 'smartphones', 'name' => 'Смартфоны', 'children' => [
                            ['slug' => 'android-phones', 'name' => 'Android'],
                            ['slug' => 'ios-phones', 'name' => 'iPhone'],
                        ]],
                        ['slug' => 'laptops', 'name' => 'Ноутбуки', 'children' => [
                            ['slug' => 'gaming-laptops', 'name' => 'Игровые'],
                            ['slug' => 'ultrabooks', 'name' => 'Ультрабуки'],
                        ]],
                        ['slug' => 'audio', 'name' => 'Аудио', 'children' => [
                            ['slug' => 'headphones', 'name' => 'Наушники'],
                            ['slug' => 'speakers', 'name' => 'Колонки'],
                        ]],
                    ]],
                    ['slug' => 'accessories', 'name' => 'Аксессуары', 'children' => [
                        ['slug' => 'cases', 'name' => 'Чехлы'],
                        ['slug' => 'chargers', 'name' => 'Зарядки'],
                    ]],
                ],
                'products' => [
                    ['APL-IP15P-256', 'Apple iPhone 15 Pro', 'ios-phones', 1199.99, 25, ['brand' => 'Apple', 'color' => 'Natural Titanium', 'storage' => '256GB']],
                    ['SMS-S24U-512', 'Samsung Galaxy S24 Ultra', 'android-phones', 1299.00, 10, ['brand' => 'Samsung', 'color' => 'Titanium Black', 'storage' => '512GB']],
                    ['APL-MBP16-M3M', 'Apple MacBook Pro 16 M3 Max', 'ultrabooks', 3499.50, 4, ['brand' => 'Apple', 'cpu' => 'M3 Max', 'ram' => '36GB']],
                    ['PX-8P-128', 'Google Pixel 8 Pro', 'android-phones', 899.00, 18, ['brand' => 'Google', 'color' => 'Obsidian', 'storage' => '128GB']],
                    ['XI-14-256', 'Xiaomi 14', 'android-phones', 749.00, 30, ['brand' => 'Xiaomi', 'color' => 'Black', 'storage' => '256GB']],
                    ['NK-G3-256', 'Nothing Phone (2a)', 'android-phones', 399.00, 40, ['brand' => 'Nothing', 'color' => 'White', 'storage' => '256GB']],
                    ['APL-IP14-128', 'Apple iPhone 14', 'ios-phones', 699.00, 15, ['brand' => 'Apple', 'color' => 'Blue', 'storage' => '128GB']],
                    ['APL-IPSE-64', 'Apple iPhone SE', 'ios-phones', 429.00, 22, ['brand' => 'Apple', 'color' => 'Midnight', 'storage' => '64GB']],
                    ['APL-IP16-256', 'Apple iPhone 16', 'ios-phones', 999.00, 12, ['brand' => 'Apple', 'color' => 'Ultramarine', 'storage' => '256GB']],
                    ['ASUS-TUF-F15', 'ASUS TUF Gaming F15', 'gaming-laptops', 1099.00, 8, ['brand' => 'ASUS', 'cpu' => 'Intel i7', 'ram' => '16GB']],
                    ['LEN-LOQ-15', 'Lenovo LOQ 15', 'gaming-laptops', 949.00, 11, ['brand' => 'Lenovo', 'cpu' => 'Ryzen 7', 'ram' => '16GB']],
                    ['MSI-KAT-15', 'MSI Katana 15', 'gaming-laptops', 1190.00, 6, ['brand' => 'MSI', 'gpu' => 'RTX 4060', 'ram' => '16GB']],
                    ['LEN-X1-14', 'Lenovo ThinkPad X1 Carbon', 'ultrabooks', 1590.00, 7, ['brand' => 'Lenovo', 'cpu' => 'Intel Ultra 7', 'weight' => '1.12kg']],
                    ['DEL-XPS-13', 'Dell XPS 13', 'ultrabooks', 1399.00, 9, ['brand' => 'Dell', 'screen' => '13.4 OLED', 'ram' => '16GB']],
                    ['HUA-MATE-D14', 'Huawei MateBook D14', 'ultrabooks', 799.00, 14, ['brand' => 'Huawei', 'cpu' => 'Intel i5', 'ram' => '16GB']],
                    ['SNY-WH1000', 'Sony WH-1000XM5', 'headphones', 349.00, 20, ['brand' => 'Sony', 'type' => 'over-ear', 'anc' => true]],
                    ['APP-AIRP-PRO', 'Apple AirPods Pro 2', 'headphones', 249.00, 35, ['brand' => 'Apple', 'type' => 'in-ear', 'anc' => true]],
                    ['JBL-TUNE-770', 'JBL Tune 770NC', 'headphones', 129.00, 28, ['brand' => 'JBL', 'type' => 'over-ear', 'anc' => true]],
                    ['SNY-SRS-XB', 'Sony SRS-XB100', 'speakers', 59.00, 50, ['brand' => 'Sony', 'type' => 'portable', 'waterproof' => true]],
                    ['JBL-FLIP-6', 'JBL Flip 6', 'speakers', 129.00, 24, ['brand' => 'JBL', 'type' => 'portable', 'waterproof' => true]],
                    ['MAR-EMB-II', 'Marshall Emberton II', 'speakers', 169.00, 16, ['brand' => 'Marshall', 'type' => 'portable', 'battery' => '30h']],
                    ['CASE-IP15-CLR', 'Чехол iPhone 15 прозрачный', 'cases', 19.90, 80, ['brand' => 'Spigen', 'material' => 'TPU', 'color' => 'Clear']],
                    ['CASE-S24-BLK', 'Чехол Galaxy S24 чёрный', 'cases', 17.50, 60, ['brand' => 'Samsung', 'material' => 'silicone', 'color' => 'Black']],
                    ['CASE-MBP-14', 'Чехол-конверт для MacBook 14', 'cases', 39.00, 25, ['brand' => 'Tomtoc', 'material' => 'polyester', 'size' => '14']],
                    ['CHG-GAN-65', 'Зарядка GaN 65W', 'chargers', 34.00, 45, ['brand' => 'Baseus', 'power' => '65W', 'ports' => 2]],
                    ['CHG-MAG-15', 'Беспроводная зарядка MagSafe 15W', 'chargers', 29.00, 33, ['brand' => 'Belkin', 'power' => '15W', 'type' => 'wireless']],
                    ['CBL-USBC-2M', 'Кабель USB-C 2 м', 'chargers', 12.00, 100, ['brand' => 'Anker', 'length' => '2m', 'power' => '100W']],
                ],
            ],
            [
                'slug' => 'home-atelier',
                'name' => 'Дом и уют',
                'city' => 'Минск',
                'address' => 'пр-т Независимости, 22',
                'phone' => '+375 29 222-22-22',
                'working_hours' => '10:00 - 21:00',
                'locations' => [
                    ['name' => 'Независимость', 'city' => 'Минск', 'address' => 'пр-т Независимости, 22', 'phone' => '+375 29 222-22-22', 'working_hours' => '10:00 - 21:00'],
                    ['name' => 'Грушевка', 'city' => 'Минск', 'address' => 'ул. Голубка, 2', 'phone' => '+375 29 222-33-33', 'working_hours' => '10:00 - 20:00'],
                ],
                'categories' => [
                    ['slug' => 'home', 'name' => 'Дом', 'children' => [
                        ['slug' => 'kitchen', 'name' => 'Кухня', 'children' => [
                            ['slug' => 'cookware', 'name' => 'Посуда'],
                            ['slug' => 'kitchen-appliances', 'name' => 'Техника'],
                        ]],
                        ['slug' => 'textile', 'name' => 'Текстиль', 'children' => [
                            ['slug' => 'bedding', 'name' => 'Постельное бельё'],
                            ['slug' => 'towels', 'name' => 'Полотенца'],
                        ]],
                    ]],
                    ['slug' => 'furniture', 'name' => 'Мебель', 'children' => [
                        ['slug' => 'living-room', 'name' => 'Гостиная'],
                        ['slug' => 'bedroom', 'name' => 'Спальня'],
                    ]],
                ],
                'products' => [
                    ['PAN-24-STEEL', 'Сковорода 24 см', 'cookware', 42.00, 30, ['brand' => 'Tefal', 'diameter' => '24cm', 'material' => 'steel']],
                    ['POT-5L-IRON', 'Кастрюля 5 л', 'cookware', 55.00, 18, ['brand' => 'Rondell', 'volume' => '5L', 'material' => 'cast iron']],
                    ['KNIFE-SET-5', 'Набор ножей 5 предметов', 'cookware', 79.00, 12, ['brand' => 'Samura', 'pieces' => 5, 'steel' => 'AUS-8']],
                    ['KETTLE-1-7', 'Чайник электрический 1.7 л', 'kitchen-appliances', 36.00, 22, ['brand' => 'Philips', 'volume' => '1.7L', 'power' => '2200W']],
                    ['BLENDER-800', 'Блендер 800 Вт', 'kitchen-appliances', 64.00, 14, ['brand' => 'Bosch', 'power' => '800W', 'type' => 'immersion']],
                    ['COFFEE-DRIP', 'Капельная кофеварка', 'kitchen-appliances', 89.00, 9, ['brand' => 'DeLonghi', 'type' => 'drip', 'cups' => 10]],
                    ['BED-SATIN-2', 'Комплект постельного белья сатин', 'bedding', 72.00, 16, ['brand' => 'Togas', 'size' => '2-спальный', 'material' => 'satin']],
                    ['BED-LINEN-1', 'Комплект белья лён', 'bedding', 95.00, 8, ['brand' => 'Askona', 'size' => 'евро', 'material' => 'linen']],
                    ['PILLOW-DOWN', 'Подушка пуховая 50x70', 'bedding', 48.00, 20, ['brand' => 'Askona', 'size' => '50x70', 'fill' => 'down']],
                    ['TOWEL-BATH', 'Полотенце банное 70x140', 'towels', 18.00, 40, ['brand' => 'Cleanelly', 'size' => '70x140', 'material' => 'cotton']],
                    ['TOWEL-SET-4', 'Набор полотенец 4 шт', 'towels', 32.00, 26, ['brand' => 'Tac', 'pieces' => 4, 'material' => 'cotton']],
                    ['TOWEL-KITCHEN', 'Кухонные полотенца 3 шт', 'towels', 14.00, 35, ['brand' => 'Tac', 'pieces' => 3, 'material' => 'waffle']],
                    ['SOFA-GREY-3', 'Диван трёхместный серый', 'living-room', 890.00, 3, ['brand' => 'Ikea', 'seats' => 3, 'color' => 'grey']],
                    ['TABLE-OAK-120', 'Журнальный стол дуб 120 см', 'living-room', 210.00, 6, ['brand' => 'Ikea', 'width' => '120cm', 'material' => 'oak']],
                    ['LAMP-FLOOR', 'Торшер с льняным абажуром', 'living-room', 75.00, 11, ['brand' => 'Markslojd', 'type' => 'floor', 'height' => '150cm']],
                    ['BED-160-OAK', 'Кровать 160x200 дуб', 'bedroom', 640.00, 4, ['brand' => 'Ikea', 'size' => '160x200', 'material' => 'oak']],
                    ['MATT-160', 'Матрас 160x200 средней жёсткости', 'bedroom', 420.00, 5, ['brand' => 'Askona', 'size' => '160x200', 'firmness' => 'medium']],
                    ['NIGHTSTAND', 'Тумба прикроватная', 'bedroom', 95.00, 10, ['brand' => 'Ikea', 'drawers' => 2, 'color' => 'white']],
                ],
            ],
            [
                'slug' => 'sport-line',
                'name' => 'СпортЛайн',
                'city' => 'Минск',
                'address' => 'ул. Притыцкого, 29',
                'phone' => '+375 29 333-33-33',
                'working_hours' => '09:00 - 22:00',
                'locations' => [
                    ['name' => 'Притыцкого', 'city' => 'Минск', 'address' => 'ул. Притыцкого, 29', 'phone' => '+375 29 333-33-33', 'working_hours' => '09:00 - 22:00'],
                    ['name' => 'Уручье', 'city' => 'Минск', 'address' => 'пр-т Независимости, 154', 'phone' => '+375 29 333-44-44', 'working_hours' => '10:00 - 22:00'],
                ],
                'categories' => [
                    ['slug' => 'sport', 'name' => 'Спорт', 'children' => [
                        ['slug' => 'running', 'name' => 'Бег', 'children' => [
                            ['slug' => 'running-shoes', 'name' => 'Кроссовки'],
                            ['slug' => 'running-wear', 'name' => 'Одежда'],
                        ]],
                        ['slug' => 'fitness', 'name' => 'Фитнес', 'children' => [
                            ['slug' => 'weights', 'name' => 'Гантели'],
                            ['slug' => 'yoga', 'name' => 'Йога'],
                        ]],
                        ['slug' => 'outdoor', 'name' => 'Туризм', 'children' => [
                            ['slug' => 'backpacks', 'name' => 'Рюкзаки'],
                            ['slug' => 'tents', 'name' => 'Палатки'],
                        ]],
                    ]],
                ],
                'products' => [
                    ['NIKE-PEG-41', 'Nike Pegasus 41', 'running-shoes', 140.00, 18, ['brand' => 'Nike', 'type' => 'road', 'drop' => '10mm']],
                    ['ASICS-NOV-4', 'Asics Novablast 4', 'running-shoes', 150.00, 12, ['brand' => 'Asics', 'type' => 'road', 'cushion' => 'high']],
                    ['HOKA-CLIFT', 'Hoka Clifton 9', 'running-shoes', 145.00, 9, ['brand' => 'Hoka', 'type' => 'road', 'weight' => '248g']],
                    ['TSHIRT-RUN-M', 'Футболка для бега', 'running-wear', 35.00, 40, ['brand' => 'Nike', 'size' => 'M', 'material' => 'polyester']],
                    ['SHORTS-RUN-L', 'Шорты беговые', 'running-wear', 42.00, 28, ['brand' => 'Adidas', 'size' => 'L', 'material' => 'polyester']],
                    ['JACKET-WIND', 'Ветровка беговая', 'running-wear', 89.00, 7, ['brand' => 'Salomon', 'size' => 'M', 'waterproof' => false]],
                    ['DUMB-10-PAIR', 'Гантели 10 кг пара', 'weights', 55.00, 15, ['brand' => 'Torres', 'weight' => '10kg', 'type' => 'neoprene']],
                    ['DUMB-5-PAIR', 'Гантели 5 кг пара', 'weights', 32.00, 20, ['brand' => 'Torres', 'weight' => '5kg', 'type' => 'neoprene']],
                    ['KETTLE-16', 'Гиря 16 кг', 'weights', 48.00, 8, ['brand' => 'Iron', 'weight' => '16kg', 'material' => 'cast iron']],
                    ['YOGA-MAT-6', 'Коврик для йоги 6 мм', 'yoga', 28.00, 36, ['brand' => 'Reebok', 'thickness' => '6mm', 'material' => 'TPE']],
                    ['YOGA-BLOCK', 'Блок для йоги', 'yoga', 12.00, 44, ['brand' => 'Reebok', 'material' => 'foam', 'color' => 'purple']],
                    ['YOGA-STRAP', 'Ремень для йоги', 'yoga', 9.00, 50, ['brand' => 'Reebok', 'length' => '180cm', 'material' => 'cotton']],
                    ['PACK-30L', 'Рюкзак туристический 30 л', 'backpacks', 79.00, 13, ['brand' => 'Deuter', 'volume' => '30L', 'waterproof' => true]],
                    ['PACK-50L', 'Рюкзак 50 л', 'backpacks', 129.00, 6, ['brand' => 'Osprey', 'volume' => '50L', 'frame' => true]],
                    ['PACK-CITY-20', 'Городской рюкзак 20 л', 'backpacks', 49.00, 22, ['brand' => 'Xiaomi', 'volume' => '20L', 'laptop' => '15']],
                    ['TENT-2P', 'Палатка двухместная', 'tents', 160.00, 5, ['brand' => 'Naturehike', 'persons' => 2, 'season' => 3]],
                    ['TENT-3P', 'Палатка трёхместная', 'tents', 220.00, 3, ['brand' => 'Tramp', 'persons' => 3, 'season' => 3]],
                    ['TENT-TARP', 'Тент 3x3 м', 'tents', 45.00, 11, ['brand' => 'Tramp', 'size' => '3x3', 'waterproof' => true]],
                ],
            ],
        ];
    }
}
