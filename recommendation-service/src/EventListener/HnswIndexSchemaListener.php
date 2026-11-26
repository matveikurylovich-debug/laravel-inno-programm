<?php

declare(strict_types=1);

namespace App\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\TableEditor;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;

/**
 * Exposes idx_products_embedding_hnsw (USING hnsw, vector_cosine_ops) to Doctrine.
 * DBAL only introspects the indexed column, so the mapping uses that same name and column.
 */
#[AsDoctrineListener(event: ToolEvents::postGenerateSchema)]
final class HnswIndexSchemaListener
{
    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        $schema = $event->getSchema();
        if (!$schema->hasTable('product_projections')) {
            return;
        }

        $table = $schema->getTable('product_projections');
        if ($table->hasIndex('idx_products_embedding_hnsw')) {
            return;
        }

        $event->setSchema($schema->edit()->modifyTable($table->getObjectName(), static function (TableEditor $editor): void {
            $editor->addIndex(Index::editor()
                ->setUnquotedName('idx_products_embedding_hnsw')
                ->setUnquotedColumnNames('embedding')
                ->create());
        })->create());
    }
}
