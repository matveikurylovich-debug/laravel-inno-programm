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

    public function __construct(#[ORM\Column(name: 'user_id', type: Types::INTEGER)]
        private int $userId, #[ORM\Column(name: 'store_id', type: Types::INTEGER)]
        private int $storeId, ?DateTimeImmutable $now = null)
    {
        $createdAt = $now ?? new DateTimeImmutable();
        $this->id = Uuid::v7();
        $this->createdAt = $createdAt;
        $this->lastActivityAt = $createdAt;
        $this->expiresAt = $createdAt->modify('+24 hours');
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

    public function touchActivity(?DateTimeImmutable $now = null): void
    {
        $this->lastActivityAt = $now ?? new DateTimeImmutable();
    }

    public function isExpired(int $ttlMinutes = 30, ?DateTimeImmutable $now = null): bool
    {
        $currentTime = $now ?? new DateTimeImmutable();
        $inactivityLimit = $this->lastActivityAt->modify(\sprintf('+%d minutes', $ttlMinutes));

        return $currentTime > $inactivityLimit || $currentTime > $this->expiresAt;
    }

    public function addMessage(Message $message, ?DateTimeImmutable $now = null): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setConversation($this);
        }

        $this->touchActivity($now);

        return $this;
    }
}
