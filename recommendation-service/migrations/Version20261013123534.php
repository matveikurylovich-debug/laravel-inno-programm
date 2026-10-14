<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class VersionXXXXXXXXXXXXXX extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Инициализация pgvector, проекций каталога и истории диалогов';
    }

    public function up(Schema $schema): void
    {
        // 1. Активация расширения pgvector
        $this->addSql('CREATE EXTENSION IF NOT EXISTS vector;');

        // 2. Таблица проекций товаров каталога
        $this->addSql('
            CREATE TABLE product_projections (
                product_id INT PRIMARY KEY,
                store_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT NULL,
                price NUMERIC(12, 2) NOT NULL,
                category_name VARCHAR(100) NOT NULL,
                attributes JSONB NOT NULL DEFAULT \'{}\',
                is_published BOOLEAN NOT NULL DEFAULT TRUE,
                is_in_stock BOOLEAN NOT NULL DEFAULT TRUE,
                product_url VARCHAR(500) NOT NULL,
                embedding vector(768) NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW()
            );
        ');

        // HNSW индекс для быстрого косинусного поиска
        $this->addSql('
            CREATE INDEX idx_products_embedding_hnsw 
            ON product_projections 
            USING hnsw (embedding vector_cosine_ops) 
            WITH (m = 16, ef_construction = 64);
        ');

        $this->addSql('CREATE INDEX idx_product_filters ON product_projections (store_id, is_published, is_in_stock);');

        // 3. Таблица контекстов диалогов (TTL 30 минут / 24 часа)
        $this->addSql('
            CREATE TABLE conversations (
                id UUID PRIMARY KEY,
                user_id VARCHAR(64) NOT NULL,
                store_id INT NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                last_activity_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            );
        ');

        $this->addSql('CREATE INDEX idx_conversations_user ON conversations (user_id);');
        $this->addSql('CREATE INDEX idx_conversations_cleanup ON conversations (last_activity_at, expires_at);');

        // 4. Таблица сообщений
        $this->addSql('
            CREATE TABLE messages (
                id UUID PRIMARY KEY,
                conversation_id UUID NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
                role VARCHAR(20) NOT NULL,
                content TEXT NOT NULL,
                extracted_filters JSONB NULL,
                suggested_products JSONB NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            );
        ');

        $this->addSql('CREATE INDEX idx_messages_conversation ON messages (conversation_id);');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS messages;');
        $this->addSql('DROP TABLE IF EXISTS conversations;');
        $this->addSql('DROP TABLE IF EXISTS product_projections;');
    }
}