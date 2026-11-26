<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MessageRole;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'messages')]
#[ORM\Index(name: 'idx_messages_conversation_created', columns: ['conversation_id', 'created_at'])]
class Message
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(name: 'extracted_filters', type: Types::JSON)]
    private array $extractedFilters = [];

    /**
     * @var list<array<string, mixed>>
     */
    #[ORM\Column(name: 'suggested_products', type: Types::JSON)]
    private array $suggestedProducts = [];

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Conversation::class, inversedBy: 'messages')]
        #[ORM\JoinColumn(name: 'conversation_id', nullable: false, onDelete: 'CASCADE')]
        private Conversation $conversation,
        #[ORM\Column(length: 32, enumType: MessageRole::class)]
        private MessageRole $role,
        #[ORM\Column(type: Types::TEXT)]
        private string $content,
        ?DateTimeImmutable $createdAt = null,
    ) {
        $this->id = Uuid::v7();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getConversation(): Conversation
    {
        return $this->conversation;
    }

    public function setConversation(Conversation $conversation): self
    {
        $this->conversation = $conversation;

        return $this;
    }

    public function getRole(): MessageRole
    {
        return $this->role;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtractedFilters(): array
    {
        return $this->extractedFilters;
    }

    /**
     * @param array<string, mixed> $extractedFilters
     */
    public function setExtractedFilters(array $extractedFilters): self
    {
        $this->extractedFilters = $extractedFilters;

        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getSuggestedProducts(): array
    {
        return $this->suggestedProducts;
    }

    /**
     * @param list<array<string, mixed>> $suggestedProducts
     */
    public function setSuggestedProducts(array $suggestedProducts): self
    {
        $this->suggestedProducts = $suggestedProducts;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
