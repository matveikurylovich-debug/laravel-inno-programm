<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ConversationRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\Table(name: 'conversations')]
#[ORM\Index(name: 'idx_conversations_user', columns: ['user_id', 'store_id'])]
#[ORM\Index(name: 'idx_conversations_ttl', columns: ['last_activity_at', 'expires_at'])]
class Conversation
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'user_id', type: Types::INTEGER)]
    private int $userId;

    #[ORM\Column(name: 'store_id', type: Types::INTEGER)]
    private int $storeId;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'last_activity_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $lastActivityAt;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $expiresAt;

    /**
     * @var Collection<int, Message>
     */
    #[ORM\OneToMany(
        targetEntity: Message::class,
        mappedBy: 'conversation',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $messages;

    public function __construct(int $userId, int $storeId)
    {
        $this->id = Uuid::v7();
        $this->userId = $userId;
        $this->storeId = $storeId;
        $this->createdAt = new DateTimeImmutable();
        $this->lastActivityAt = $this->createdAt;
        $this->expiresAt = $this->createdAt->modify('+24 hours');
        $this->messages = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getStoreId(): int
    {
        return $this->storeId;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastActivityAt(): DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function touchActivity(): void
    {
        $this->lastActivityAt = new DateTimeImmutable();
    }

    public function isExpired(): bool
    {
        $now = new DateTimeImmutable();
        $inactivityLimit = $this->lastActivityAt->modify('+30 minutes');

        return $now > $inactivityLimit || $now > $this->expiresAt;
    }

    public function addMessage(Message $message): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setConversation($this);
        }

        $this->touchActivity();

        return $this;
    }
}
