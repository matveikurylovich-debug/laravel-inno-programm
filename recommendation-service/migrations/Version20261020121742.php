<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261020121742 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS vector');

        $this->addSql('CREATE TABLE conversations (id UUID NOT NULL, user_id INT NOT NULL, store_id INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_activity_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_conversations_user ON conversations (user_id, store_id)');
        $this->addSql('CREATE INDEX idx_conversations_ttl ON conversations (last_activity_at, expires_at)');
        $this->addSql('CREATE TABLE messages (id UUID NOT NULL, role VARCHAR(32) NOT NULL, content TEXT NOT NULL, extracted_filters JSON NOT NULL, suggested_products JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, conversation_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_messages_conversation_created ON messages (conversation_id, created_at)');
        $this->addSql('CREATE INDEX IDX_DB021E969AC0396 ON messages (conversation_id)');
        $this->addSql('CREATE TABLE product_projections (product_id INT NOT NULL, store_id INT NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, price NUMERIC(12, 2) NOT NULL, category_name VARCHAR(150) NOT NULL, attributes JSON NOT NULL, is_published BOOLEAN DEFAULT true NOT NULL, is_in_stock BOOLEAN DEFAULT true NOT NULL, product_url VARCHAR(512) NOT NULL, embedding vector(768) DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (product_id))');
        $this->addSql('CREATE INDEX idx_products_store_stock ON product_projections (store_id, is_published, is_in_stock)');
        $this->addSql('CREATE INDEX idx_products_embedding_hnsw ON product_projections USING hnsw (embedding vector_cosine_ops)');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_DB021E969AC0396 FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE messages DROP CONSTRAINT FK_DB021E969AC0396');
        $this->addSql('DROP TABLE conversations');
        $this->addSql('DROP TABLE messages');
        $this->addSql('DROP TABLE product_projections');
    }
}
