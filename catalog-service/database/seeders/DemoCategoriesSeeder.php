<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Seeder;

class DemoCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        Model::withoutEvents(function (): void {
            foreach (DemoCatalogFixtures::stores() as $storeData) {
                $store = Store::query()->where('slug', $storeData['slug'])->first();

                if (! $store instanceof Store) {
                    throw new ModelNotFoundException("Магазин {$storeData['slug']} не найден. Сначала запустите DemoStoresSeeder.");
                }

                $this->seedTree($store, $storeData['categories'], null);
            }
        });
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private function seedTree(Store $store, array $nodes, ?int $parentId): void
    {
        foreach ($nodes as $position => $node) {
            $slug = is_string($node['slug'] ?? null) ? $node['slug'] : '';
            $name = is_string($node['name'] ?? null) ? $node['name'] : '';
            $category = Category::query()->updateOrCreate(
                ['store_id' => $store->id, 'slug' => $slug],
                [
                    'parent_id' => $parentId,
                    'name' => $name,
                    'position' => $position + 1,
                ],
            );

            /** @var list<array<string, mixed>> $children */
            $children = is_array($node['children'] ?? null) ? array_values($node['children']) : [];
            $this->seedTree($store, $children, $category->id);
        }
    }
}
