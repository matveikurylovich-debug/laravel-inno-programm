<?php

namespace App\Contracts;

interface ProductIndex
{
    public function indexName(): string;

    /**
     * @param  array<string, mixed>  $document
     */
    public function upsert(string $id, array $document): void;

    public function delete(string $id): void;

    public function updateInStock(string $id, bool $inStock): void;

    public function renameCategory(int $categoryId, string $name): void;

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, miкxed>
     */
    public function search(array $query): array;

    public function setup(bool $force = false): void;
}


