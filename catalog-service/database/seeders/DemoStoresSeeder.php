<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Store;
use App\Models\StoreLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class DemoStoresSeeder extends Seeder
{
    public function run(): void
    {
        Model::withoutEvents(function (): void {
            foreach (DemoCatalogFixtures::stores() as $storeData) {
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

                $this->seedLocations($store, $storeData['locations']);
            }
        });
    }

    /**
     * @param  list<array{name: string, city: string, address: string, phone: string, working_hours: string}>  $locations
     */
    private function seedLocations(Store $store, array $locations): void
    {
        foreach ($locations as $location) {
            StoreLocation::query()->updateOrCreate(
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
    }
}
