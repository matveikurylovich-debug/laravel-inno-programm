<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Exceptions\SearchFailedException;
use App\Exceptions\SearchUnavailableException;

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

    public function clearCategory(int $categoryId): void;

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws SearchUnavailableException
     * @throws SearchFailedException
     */
    public function search(array $query): array;

    public function setup(bool $force = false): void;

    /**
     * @param  iterable<int, array{id: string, document: array<string, mixed>}>  $documents
     */
    public function rebuild(iterable $documents): void;
}
