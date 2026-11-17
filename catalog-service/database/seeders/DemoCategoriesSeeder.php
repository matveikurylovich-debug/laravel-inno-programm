<?php

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
     * @param  list<array{slug: string, name: string, children?: list<array<string, mixed>>}>  $nodes
     */
    private function seedTree(Store $store, array $nodes, ?int $parentId): void
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

            $this->seedTree($store, $node['children'] ?? [], $category->id);
        }
    }
}
